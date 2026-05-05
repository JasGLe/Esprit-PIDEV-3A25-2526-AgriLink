<?php

namespace App\Controller\Equipment;

use App\Entity\Maintenance;
use App\Form\Equipment\MaintenanceType;
use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use App\Service\Equipment\ExchangeRateService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MaintenanceController
 * ─────────────────────────────────────────────────────────────────────────────
 * Gère le CRUD complet des maintenances agricoles pour l'agriculteur connecté.
 *
 * Responsabilités :
 *  - Lister les maintenances avec KPIs globaux (total, en retard, coût total),
 *    pagination (8/page), filtres (search, statut, type), carte équipement,
 *    et widget de conversion de devises (ExchangeRateService).
 *  - Créer une maintenance avec pré-remplissage de l'équipement si on vient
 *    depuis la fiche équipement (/new/{equipementId}).
 *  - Afficher une maintenance avec le widget de conversion des devises.
 *  - Modifier et supprimer une maintenance avec vérification CSRF.
 *
 * Sécurité :
 *  - Toutes les opérations passent par denyAccessUnlessOwner() qui charge
 *    l'équipement associé et compare son userlog avec l'utilisateur connecté.
 *  - La suppression vérifie le token CSRF ('delete{id}').
 *
 * Route prefix : /maintenance  (name prefix : maintenance_)
 * ─────────────────────────────────────────────────────────────────────────────
 */
#[Route('/maintenance', name: 'maintenance_')]
class MaintenanceController extends AbstractController
{
    /**
     * Liste paginée de toutes les maintenances de l'agriculteur connecté.
     *
     * Fonctionnement :
     *  1. Récupère tous les équipements de l'utilisateur pour en extraire les IDs.
     *  2. Calcule les KPIs globaux : total maintenances, nombre en retard, coût total.
     *  3. Exécute la requête paginée (8/page) avec filtres optionnels.
     *  4. Construit la map id => équipement pour l'affichage dans les cartes.
     *  5. Récupère les taux de change (mis en cache 1h) pour les dropdowns de conversion.
     *
     * @param MaintenanceRepository $repo               Accès aux maintenances en base
     * @param EquipementRepository  $equipRepo          Accès aux équipements pour filtrer par propriétaire
     * @param PaginatorInterface    $paginator          Pagination KnpPaginator
     * @param ExchangeRateService   $exchangeRateService Taux de change TND → devises (cache 1h)
     * @param Request               $request            Paramètres URL (search, statut, type, page)
     *
     * @return Response  Vue equipment/maintenance/index.html.twig avec KPIs, cartes et taux
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        MaintenanceRepository $repo,
        EquipementRepository  $equipRepo,
        PaginatorInterface    $paginator,
        ExchangeRateService   $exchangeRateService,
        Request               $request
    ): Response {
        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user   = $this->getUser();
        $userId = $user->getId();

        // ── Récupérer tous les équipements de l'utilisateur ───────────────────
        $equipements   = $equipRepo->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        // ── Filtres URL ────────────────────────────────────────────────────────
        $search = trim((string) $request->query->get('search', '')); // Fix PHPStan — get() retourne mixed, trim() attend string
        $statut = (string) $request->query->get('statut', ''); // Fix PHPStan
        $type   = (string) $request->query->get('type', ''); // Fix PHPStan

        // ── KPIs globaux (calculés sur TOUTES les maintenances, sans filtre) ───
        // Les filtres n'affectent que la liste paginée, pas les compteurs KPI
        $kpiTotal  = $repo->countTotalForUser($equipementIds);
        $kpiRetard = $repo->countEnRetardForUser($equipementIds);
        $kpiCout   = $repo->sumCoutForUser($equipementIds);

        // ── Pagination (8 par page, avec filtres actifs) ───────────────────────
        $maintenances = $paginator->paginate(
            $repo->findFilteredQuery($equipementIds, $search, $statut, $type),
            $request->query->getInt('page', 1),
            8
        );

        // ── Map id => équipement pour afficher le nom dans chaque carte ─────────
        $equipMap = [];
        foreach ($equipements as $eq) {
            $equipMap[$eq->getId()] = $eq;
        }

        // ── Taux de change TND → devises (mis en cache 1 heure) ─────────────────
        // Retourne [] si l'API est indisponible — les dropdowns et le panel seront masqués
        $rates = $exchangeRateService->getRatesFromTND();

        return $this->render('equipment/maintenance/index.html.twig', [
            'maintenances' => $maintenances,
            'equipMap'     => $equipMap,
            'search'       => $search,
            'statut'       => $statut,
            'type'         => $type,
            'kpiTotal'     => $kpiTotal,
            'kpiRetard'    => $kpiRetard,
            'kpiCout'      => $kpiCout,
            'rates'        => $rates,    // taux de change pour le panel et les dropdowns
        ]);
    }

    /**
     * Formulaire de création d'une nouvelle maintenance.
     *
     * Deux routes partagent cette action :
     *  - /maintenance/new              → création libre (aucun équipement pré-sélectionné)
     *  - /maintenance/new/{equipementId} → création depuis la fiche équipement
     *                                      (équipement pré-sélectionné dans le formulaire)
     *
     * Après création :
     *  - Si on venait de la fiche équipement → redirection vers cette fiche.
     *  - Sinon → redirection vers la liste des maintenances.
     *
     * @param Request                $request      Requête HTTP
     * @param EntityManagerInterface $em           Gestionnaire d'entités Doctrine
     * @param int                    $equipementId ID de l'équipement (0 si route libre)
     *
     * @return Response  Formulaire (GET) ou redirection (POST valide)
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Route('/new/{equipementId}', name: 'new_for_equipement', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        int $equipementId = 0
    ): Response {
        $maintenance = new Maintenance();

        // ── Pré-sélectionner l'équipement si on vient de sa fiche ───────────────
        if ($equipementId > 0) {
            $maintenance->setEquipementId($equipementId);
        }

        // Le formulaire filtre les équipements par user_id pour n'afficher
        // que ceux qui appartiennent à l'agriculteur connecté
        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user = $this->getUser();
        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $user->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normaliser cout null → '0.00' pour éviter une violation NOT NULL en base
            if ($maintenance->getCout() === null) {
                $maintenance->setCout('0.00');
            }
            $maintenance->setUserlog($user->getId());
            $em->persist($maintenance);
            $em->flush();

            $this->addFlash('success', 'Maintenance planifiée avec succès !');

            // Retourner à la fiche équipement si on en venait
            if ($equipementId > 0) {
                return $this->redirectToRoute('equipement_show', ['id' => $equipementId]);
            }
            return $this->redirectToRoute('maintenance_index');
        }

        return $this->render('equipment/maintenance/new.html.twig', [
            'form'         => $form,
            'maintenance'  => $maintenance,
            'equipementId' => $equipementId,
        ]);
    }

    /**
     * Fiche détaillée d'une maintenance avec widget de conversion de devises.
     *
     * Affiche toutes les informations de la maintenance ainsi qu'un widget
     * de conversion en temps réel du coût (TND → USD, EUR, AED, SAR, GBP, KWD).
     * Le widget est masqué si cout = 0 ou si l'API de taux est indisponible.
     *
     * @param Maintenance        $maintenance         Entité résolue via ParamConverter
     * @param EquipementRepository $equipRepo         Pour charger l'équipement lié et vérifier le propriétaire
     * @param ExchangeRateService  $exchangeRateService Taux de change (cache 1h)
     *
     * @return Response  Vue equipment/maintenance/show.html.twig
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Maintenance         $maintenance,
        EquipementRepository $equipRepo,
        ExchangeRateService  $exchangeRateService
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        $equipement = $equipRepo->find($maintenance->getEquipementId());

        // ── Taux de change TND → devises (mis en cache 1 heure) ─────────────────
        // Retourne [] si l'API est indisponible — le widget sera masqué dans le template
        $rates = $exchangeRateService->getRatesFromTND();

        return $this->render('equipment/maintenance/show.html.twig', [
            'maintenance' => $maintenance,
            'equipement'  => $equipement,
            'rates'       => $rates,    // taux de change pour le widget de conversion
        ]);
    }

    /**
     * Formulaire de modification d'une maintenance existante.
     *
     * Le formulaire filtre les équipements par user_id pour ne permettre
     * de ré-assigner la maintenance qu'à un équipement du même utilisateur.
     * Après modification réussie : redirection vers la fiche de la maintenance.
     *
     * @param Request                $request     Requête HTTP
     * @param Maintenance            $maintenance Entité à modifier (ParamConverter)
     * @param EntityManagerInterface $em          Gestionnaire d'entités Doctrine
     * @param EquipementRepository   $equipRepo   Pour charger l'équipement lié et vérifier le propriétaire
     *
     * @return Response  Formulaire (GET/POST invalide) ou redirection (POST valide)
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Maintenance $maintenance,
        EntityManagerInterface $em,
        EquipementRepository $equipRepo
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user = $this->getUser();
        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $user->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normaliser cout null → '0.00' pour éviter une violation NOT NULL en base
            if ($maintenance->getCout() === null) {
                $maintenance->setCout('0.00');
            }
            $em->flush();
            $this->addFlash('success', 'Maintenance modifiée avec succès !');
            return $this->redirectToRoute('maintenance_index');
        }

        return $this->render('equipment/maintenance/edit.html.twig', [
            'form'        => $form,
            'maintenance' => $maintenance,
            'equipement'  => $equipRepo->find($maintenance->getEquipementId()),
        ]);
    }

    /**
     * Suppression d'une maintenance après vérification CSRF.
     *
     * Sécurité :
     *  - denyAccessUnlessOwner() : seul le propriétaire de l'équipement associé peut supprimer.
     *  - Token CSRF 'delete{id}' : protège contre les suppressions forgées.
     *
     * Note : après suppression, on redirige vers la liste générale des maintenances
     * (pas vers la fiche équipement) car l'équipement peut ne plus exister.
     *
     * @param Request                $request     Requête POST avec le champ _token
     * @param Maintenance            $maintenance Entité à supprimer (ParamConverter)
     * @param EntityManagerInterface $em          Gestionnaire d'entités Doctrine
     * @param EquipementRepository   $equipRepo   Pour vérifier le propriétaire via l'équipement lié
     *
     * @return Response  Redirection vers la liste des maintenances
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Maintenance $maintenance,
        EntityManagerInterface $em,
        EquipementRepository $equipRepo
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        $equipementId = $maintenance->getEquipementId();

        if ($this->isCsrfTokenValid('delete' . $maintenance->getId(), $request->request->getString('_token'))) { // Fix PHPStan — getString() retourne string (get() retourne mixed)
            $em->remove($maintenance);
            $em->flush();
            $this->addFlash('success', 'Maintenance supprimée.');
        }

        return $this->redirectToRoute('maintenance_index');
    }

    /**
     * Vérifie que l'utilisateur connecté est le propriétaire de la maintenance.
     *
     * Logique : une maintenance appartient indirectement à un utilisateur via son
     * équipement (Maintenance::equipementId → Equipement::userlog).
     * Cette méthode charge l'équipement depuis la base et compare son userlog
     * avec l'ID de l'utilisateur connecté.
     *
     * Lève une exception AccessDeniedException (403) si :
     *  - L'équipement associé n'existe plus en base.
     *  - L'équipement appartient à un autre utilisateur.
     *
     * @param Maintenance        $maintenance Maintenance dont on vérifie la propriété
     * @param EquipementRepository $equipRepo Pour charger l'équipement associé
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException  Si l'accès est refusé
     */
    private function denyAccessUnlessOwner(
        Maintenance $maintenance,
        EquipementRepository $equipRepo
    ): void {
        $equipement = $equipRepo->find($maintenance->getEquipementId());
        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user = $this->getUser();
        if (!$equipement || $equipement->getUserlog() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'Vous n\'avez pas accès à cette maintenance.'
            );
        }
    }
}
