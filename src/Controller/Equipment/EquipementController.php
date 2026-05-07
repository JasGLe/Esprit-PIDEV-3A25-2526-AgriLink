<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Form\Equipment\EquipementType;
use App\Notification\EquipementStatutNotification;
use App\Repository\EquipementRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * EquipementController
 * ─────────────────────────────────────────────────────────────────────────────
 * Gère le CRUD complet des équipements agricoles pour l'agriculteur connecté.
 *
 * Responsabilités :
 *  - Lister les équipements avec pagination (6/page), filtres texte + statut
 *    et préparer les données pour la carte géographique Leaflet.
 *  - Créer un équipement avec upload photo, géolocalisation (lat/lng via JS),
 *    et effacement automatique des champs véhicule si catégorie non-motorisée.
 *  - Modifier un équipement avec envoi SMS Twilio si le statut change.
 *  - Supprimer un équipement avec vérification CSRF.
 *  - Fournir un endpoint JSON dynamique pour charger les types selon la catégorie.
 *
 * Sécurité :
 *  - Toutes les actions de lecture/modification/suppression passent par
 *    denyAccessUnlessOwner() qui compare Equipement::userlog avec l'ID connecté.
 *  - Le token CSRF est vérifié sur la suppression.
 *
 * Route prefix : /equipement  (name prefix : equipement_)
 * ─────────────────────────────────────────────────────────────────────────────
 */
#[Route('/equipement', name: 'equipement_')]
class EquipementController extends AbstractController
{
    /**
     * Liste paginée des équipements de l'agriculteur connecté.
     *
     * Fonctionnement :
     *  1. Récupère l'utilisateur connecté et ses filtres URL (search, statut).
     *  2. Construit un QueryBuilder filtré par userlog + critères optionnels.
     *  3. Pagine le résultat (6 éléments / page) via KnpPaginator.
     *  4. Calcule les tokens CSRF pour les boutons "Vendre en boutique".
     *  5. Récupère tous les équipements géolocalisés pour la carte Leaflet.
     *
     * @param Request              $request     Paramètres URL (search, statut, page)
     * @param EquipementRepository $repo        Accès aux équipements en base
     * @param ProduitsRepository   $produitsRepository  Vérifie quels équipements sont déjà en boutique
     * @param PaginatorInterface   $paginator   Pagination KnpPaginator
     *
     * @return Response  Vue equipment/index.html.twig
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        EquipementRepository $repo,
        ProduitsRepository $produitsRepository,
        PaginatorInterface $paginator
    ): Response {
        $user = $this->getUser();
        if ($user === null || $user->getId() === null) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $search    = $request->query->get('search', '');
        $statut    = $request->query->get('statut', '');
        $userId    = $user->getId();

        // ── Requête de base : uniquement les équipements de l'utilisateur connecté ──
        $qb = $repo->createQueryBuilder('e')
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.dateCreation', 'DESC');

        // ── Filtre recherche texte (nom, type, marque) ──────────────────────────
        if ($search !== '') {
            $qb->andWhere(
                'e.nom LIKE :search OR e.type LIKE :search OR e.marque LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        // ── Filtre statut (Actif, En panne, En maintenance, Hors service) ───────
        if ($statut !== '') {
            $qb->andWhere('e.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // ── Pagination : 6 équipements par page ─────────────────────────────────
        $equipements = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            6
        );

        // ── Équipements déjà en vente en boutique (pour désactiver le bouton) ───
        $equipementIdsEnBoutique = [];
        $equipementIdsEnBoutique = $produitsRepository->findEquipementIdsAlreadyInBoutiqueByOwner($userId);

        // ── Tokens CSRF pour les formulaires "Mettre en vente" ──────────────────
        // Un token unique par équipement pour prévenir les soumissions forgées
        $boutiqueTokens = [];
        foreach ($equipements as $eq) {
            $boutiqueTokens[(string) $eq->getId()] = $this->container->get('security.csrf.token_manager')
                ->getToken('boutique_equipement_vente_' . $eq->getId())
                ->getValue();
        }

        // ── Équipements géolocalisés pour la carte Leaflet (tous, sans filtre) ──
        // Seuls les équipements avec lat+lng non-null sont inclus
        $equipementsGeo = [];
        $allGeo = $repo->createQueryBuilder('g')
            ->where('g.userlog = :userId')
            ->andWhere('g.latitude IS NOT NULL')
            ->andWhere('g.longitude IS NOT NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
        foreach ($allGeo as $geo) {
            $equipementsGeo[] = [
                'id'     => $geo->getId(),
                'nom'    => $geo->getNom(),
                'type'   => $geo->getType(),
                'marque' => $geo->getMarque(),
                'statut' => $geo->getStatut(),
                'lat'    => $geo->getLatitude(),
                'lng'    => $geo->getLongitude(),
                'url'    => $this->generateUrl('equipement_show', ['id' => $geo->getId()]),
            ];
        }

        return $this->render('equipment/index.html.twig', [
            'equipements'                => $equipements,
            'equipement_ids_en_boutique' => $equipementIdsEnBoutique,
            'equipement_boutique_tokens' => $boutiqueTokens,
            'search'                     => $search,
            'statut'                     => $statut,
            'equipementsGeo'             => $equipementsGeo,
        ]);
    }

    /**
     * Formulaire de création d'un nouvel équipement.
     *
     * Fonctionnement :
     *  1. GET  : affiche le formulaire vide avec la clé API OpenCage pour la géolocalisation.
     *  2. POST : valide le formulaire, uploade la photo si fournie,
     *            efface les champs véhicule si catégorie ≠ "Véhicule Motorisé",
     *            et persiste l'équipement avec l'userlog de l'utilisateur connecté.
     *
     * Effacement des champs véhicule :
     *  Si l'utilisateur sélectionne une catégorie non-motorisée, le JS masque les
     *  champs véhicule dans le formulaire, mais pour sécurité on les efface aussi
     *  côté serveur avant la persistance.
     *
     * @param Request                $request      Requête HTTP
     * @param EntityManagerInterface $em           Gestionnaire d'entités Doctrine
     * @param FileUploader           $fileUploader Service d'upload d'image
     *
     * @return Response  Formulaire (GET) ou redirection vers index (POST valide)
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        FileUploader $fileUploader
    ): Response {
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ── Upload de la photo (non obligatoire) ────────────────────────────
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $imagePath = $fileUploader->upload($imageFile, 'equipements');
                    $equipement->setImageUrl($imagePath);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }

            // ── Effacer les champs véhicule si catégorie non motorisée ───────────
            // Évite de conserver des données incohérentes en base si l'utilisateur
            // a changé de catégorie après avoir renseigné des km/heures
            if (!$equipement->isVehicule()) {
                $equipement
                    ->setKilometrageActuel(null)
                    ->setKilometrageDerniereMaintenance(null)
                    ->setSeuilKmMaintenance(null)
                    ->setHeuresUtilisation(null)
                    ->setHeuresDerniereMaintenance(null)
                    ->setSeuilHeuresMaintenance(null);
            }

            // ── Lier l'équipement à l'utilisateur connecté ──────────────────────
            /** @var \App\Entity\UserManagement\User $currentUser */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
            $currentUser = $this->getUser();
            $equipement->setUserlog($currentUser->getId());
            $em->persist($equipement);
            $em->flush();

            $this->addFlash('success', 'Équipement ajouté avec succès !');
            return $this->redirectToRoute('equipement_index');
        }

        return $this->render('equipment/new.html.twig', [
            'form'           => $form,
            'equipement'     => $equipement,
            'opencageApiKey' => $this->getParameter('opencage_api_key'),
        ]);
    }

    /**
     * Fiche détaillée d'un équipement.
     *
     * Sécurité : vérifie que l'équipement appartient à l'utilisateur connecté.
     *
     * @param Equipement $equipement  Résolu automatiquement via ParamConverter (id dans l'URL)
     *
     * @return Response  Vue equipment/show.html.twig
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Equipement $equipement): Response
    {
        $this->denyAccessUnlessOwner($equipement);
        return $this->render('equipment/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }

    /**
     * Formulaire de modification d'un équipement existant.
     *
     * Fonctionnement :
     *  1. Sauvegarde l'ancien statut AVANT handleRequest pour détecter un changement.
     *  2. GET  : affiche le formulaire pré-rempli.
     *  3. POST : valide, uploade la nouvelle photo si fournie (supprime l'ancienne),
     *            efface les champs véhicule si nécessaire.
     *  4. Si le statut a changé : envoie un SMS Twilio au numéro de l'utilisateur
     *     (normalisation E.164 : 0XXXXXXXX → +216XXXXXXXX).
     *  5. Le SMS ne bloque jamais la modification en cas d'échec (catch silencieux).
     *
     * @param Request                $request      Requête HTTP
     * @param Equipement             $equipement   Entité à modifier (ParamConverter)
     * @param EntityManagerInterface $em           Gestionnaire d'entités Doctrine
     * @param FileUploader           $fileUploader Service d'upload d'image
     * @param NotifierInterface      $notifier     Service de notification Symfony (SMS Twilio)
     *
     * @return Response  Formulaire (GET/POST invalide) ou redirection vers index (POST valide)
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em,
        FileUploader $fileUploader,
        NotifierInterface $notifier
    ): Response {
        $this->denyAccessUnlessOwner($equipement);

        // Sauvegarder le statut AVANT handleRequest pour comparer après soumission
        $ancienStatut = $equipement->getStatut();

        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ── Mise à jour de la photo (remplace l'ancienne si fournie) ─────────
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    // L'ancien fichier est supprimé automatiquement par FileUploader::upload()
                    $imagePath = $fileUploader->upload($imageFile, 'equipements', $equipement->getImageUrl());
                    $equipement->setImageUrl($imagePath);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }

            // ── Effacer les champs véhicule si catégorie non motorisée ───────────
            if (!$equipement->isVehicule()) {
                $equipement
                    ->setKilometrageActuel(null)
                    ->setKilometrageDerniereMaintenance(null)
                    ->setSeuilKmMaintenance(null)
                    ->setHeuresUtilisation(null)
                    ->setHeuresDerniereMaintenance(null)
                    ->setSeuilHeuresMaintenance(null);
            }

            $em->flush();

            // ── Notification SMS si statut changé ────────────────────────────────
            // Envoie un SMS Twilio informant l'agriculteur du changement de statut
            $nouveauStatut = $equipement->getStatut();
            if ($ancienStatut !== $nouveauStatut) {
                /** @var \App\Entity\UserManagement\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
                $user      = $this->getUser();
                $telephone = $user->getTelephone(); // Fix PHPStan #310 — App\Entity\UserManagement\User::getTelephone() existe, method_exists() redondant supprimé

                if ($telephone !== null && $telephone !== '') {
                    // Normalisation E.164 : 0XXXXXXXX → +216XXXXXXXX (Tunisie)
                    if (str_starts_with($telephone, '0')) {
                        $telephone = '+216' . substr($telephone, 1);
                    } elseif (!str_starts_with($telephone, '+')) {
                        $telephone = '+216' . $telephone;
                    }

                    try {
                        $notification = new EquipementStatutNotification(
                            $equipement->getNom(), // Fix PHPStan — getNom() retourne string (non-nullable), ?? redondant supprimé
                            $ancienStatut ?? '—',
                            $nouveauStatut ?? '—'
                        );
                        $recipient = new Recipient('', $telephone);
                        $notifier->send($notification, $recipient);
                        $this->addFlash('info', 'SMS de notification envoyé.');
                    } catch (\Throwable $e) {
                        // Ne pas bloquer la modification si le SMS échoue (réseau, quota Twilio, etc.)
                    }
                }
            }

            $this->addFlash('success', 'Équipement modifié avec succès !');
            return $this->redirectToRoute('equipement_index');
        }

        return $this->render('equipment/edit.html.twig', [
            'form'           => $form,
            'equipement'     => $equipement,
            'opencageApiKey' => $this->getParameter('opencage_api_key'),
        ]);
    }

    /**
     * Suppression d'un équipement après vérification CSRF.
     *
     * Sécurité :
     *  - denyAccessUnlessOwner() : seul le propriétaire peut supprimer.
     *  - Token CSRF 'delete{id}' : protège contre les suppressions forgées (CSRF).
     *
     * @param Request                $request     Requête POST avec le champ _token
     * @param Equipement             $equipement  Entité à supprimer (ParamConverter)
     * @param EntityManagerInterface $em          Gestionnaire d'entités Doctrine
     *
     * @return Response  Redirection vers la liste des équipements
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessOwner($equipement);

        if ($this->isCsrfTokenValid('delete' . $equipement->getId(), $request->request->getString('_token'))) { // Fix PHPStan — getString() retourne string (get() retourne mixed)
            $em->remove($equipement);
            $em->flush();
            $this->addFlash('success', 'Équipement supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('equipement_index');
    }

    /**
     * API JSON : retourne les types disponibles pour une catégorie donnée.
     *
     * Utilisé par le JavaScript du formulaire new/edit pour peupler dynamiquement
     * le champ <select> "Type" quand l'utilisateur change la catégorie.
     *
     * Exemple : GET /equipement/api/types?categorie=Véhicule+Motorisé
     * Retourne : ["Tracteur", "Moissonneuse", "Pulvérisateur", ...]
     *
     * @param Request $request  Paramètre URL 'categorie'
     *
     * @return JsonResponse  Tableau JSON des types (clés de la constante correspondante)
     */
    #[Route('/api/types', name: 'api_types', methods: ['GET'])]
    public function apiTypes(Request $request): JsonResponse
    {
        $categorie = $request->query->get('categorie', 'Autre Équipement');
        $types = $categorie === 'Véhicule Motorisé'
            ? Equipement::TYPES_VEHICULES
            : Equipement::TYPES_EQUIPEMENTS;
        return $this->json(array_keys($types));
    }

    /**
     * Vérifie que l'utilisateur connecté est le propriétaire de l'équipement.
     *
     * Lève une exception AccessDeniedException (403) si la vérification échoue.
     * Appelé dans show(), edit() et delete() avant toute opération sur l'entité.
     *
     * @param Equipement $equipement  Équipement dont on vérifie la propriété
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException  Si l'accès est refusé
     */
    private function denyAccessUnlessOwner(Equipement $equipement): void
    {
        /** @var \App\Entity\UserManagement\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user = $this->getUser();
        if ($equipement->getUserlog() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet équipement.');
        }
    }
}
