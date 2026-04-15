<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Form\Equipment\EquipementType;
use App\Notification\EquipementStatutNotification;
use App\Repository\EquipementRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;  // ← AJOUT
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/equipement', name: 'equipement_')]
class EquipementController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
public function index(
    Request $request,
    EquipementRepository $repo,
    ProduitsRepository $produitsRepository,
    PaginatorInterface $paginator
): Response {
    $user      = $this->getUser();
    $search    = $request->query->get('search', '');
    $statut    = $request->query->get('statut', '');

    $qb = $repo->createQueryBuilder('e')
        ->where('e.userlog = :userId')
        ->setParameter('userId', $user->getId())
        ->orderBy('e.dateCreation', 'DESC');

    // ── Filtre recherche texte ──
    if ($search !== '') {
        $qb->andWhere(
            'e.nom LIKE :search OR e.type LIKE :search OR e.marque LIKE :search'
        )->setParameter('search', '%' . $search . '%');
    }

    // ── Filtre statut ──
    if ($statut !== '') {
        $qb->andWhere('e.statut = :statut')
           ->setParameter('statut', $statut);
    }

    $equipements = $paginator->paginate(
        $qb->getQuery(),
        $request->query->getInt('page', 1),
        6
    );

    $equipementIdsEnBoutique = [];
    if ($user !== null && method_exists($user, 'getId') && $user->getId() !== null) {
        $equipementIdsEnBoutique = $produitsRepository->findEquipementIdsAlreadyInBoutiqueByOwner((int) $user->getId());
    }

    $boutiqueTokens = [];
    foreach ($equipements as $eq) {
        $boutiqueTokens[(string) $eq->getId()] = $this->container->get('security.csrf.token_manager')
            ->getToken('boutique_equipement_vente_' . $eq->getId())
            ->getValue();
    }

    // ── Équipements géolocalisés (tous, pour la carte) ──
    $equipementsGeo = [];
    $allGeo = $repo->createQueryBuilder('g')
        ->where('g.userlog = :userId')
        ->andWhere('g.latitude IS NOT NULL')
        ->andWhere('g.longitude IS NOT NULL')
        ->setParameter('userId', $user->getId())
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

    // ── Toutes les autres méthodes restent INCHANGÉES ──

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
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $imagePath = $fileUploader->upload($imageFile, 'equipements');
                    $equipement->setImageUrl($imagePath);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }

            if (!$equipement->isVehicule()) {
                $equipement
                    ->setKilometrageActuel(null)
                    ->setKilometrageDerniereMaintenance(null)
                    ->setSeuilKmMaintenance(null)
                    ->setHeuresUtilisation(null)
                    ->setHeuresDerniereMaintenance(null)
                    ->setSeuilHeuresMaintenance(null);
            }

            $equipement->setUserlog($this->getUser()->getId());
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

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Equipement $equipement): Response
    {
        $this->denyAccessUnlessOwner($equipement);
        return $this->render('equipment/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em,
        FileUploader $fileUploader,
        NotifierInterface $notifier
    ): Response {
        $this->denyAccessUnlessOwner($equipement);

        // Sauvegarder le statut AVANT handleRequest (modification par l'utilisateur)
        $ancienStatut = $equipement->getStatut();

        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $imagePath = $fileUploader->upload($imageFile, 'equipements', $equipement->getImageUrl());
                    $equipement->setImageUrl($imagePath);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }

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

            // ── Notification SMS si statut changé ──────────────────────────
            $nouveauStatut = $equipement->getStatut();
            if ($ancienStatut !== $nouveauStatut) {
                $user      = $this->getUser();
                $telephone = method_exists($user, 'getTelephone') ? $user->getTelephone() : null;

                if ($telephone !== null && $telephone !== '') {
                    // Normaliser en E.164 : si commence par 0, remplacer par +216
                    if (str_starts_with($telephone, '0')) {
                        $telephone = '+216' . substr($telephone, 1);
                    } elseif (!str_starts_with($telephone, '+')) {
                        $telephone = '+216' . $telephone;
                    }

                    try {
                        $notification = new EquipementStatutNotification(
                            $equipement->getNom() ?? 'Équipement',
                            $ancienStatut ?? '—',
                            $nouveauStatut ?? '—'
                        );
                        $recipient = new Recipient('', $telephone);
                        $notifier->send($notification, $recipient);
                        $this->addFlash('info', 'SMS de notification envoyé.');
                    } catch (\Throwable $e) {
                        // Ne pas bloquer la modification si le SMS échoue
                    }
                }
            }
            // ───────────────────────────────────────────────────────────────

            $this->addFlash('success', 'Équipement modifié avec succès !');
            return $this->redirectToRoute('equipement_index');
        }

        return $this->render('equipment/edit.html.twig', [
            'form'           => $form,
            'equipement'     => $equipement,
            'opencageApiKey' => $this->getParameter('opencage_api_key'),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessOwner($equipement);

        if ($this->isCsrfTokenValid('delete' . $equipement->getId(), $request->request->get('_token'))) {
            $em->remove($equipement);
            $em->flush();
            $this->addFlash('success', 'Équipement supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('equipement_index');
    }

    #[Route('/api/types', name: 'api_types', methods: ['GET'])]
    public function apiTypes(Request $request): JsonResponse
    {
        $categorie = $request->query->get('categorie', 'Autre Équipement');
        $types = $categorie === 'Véhicule Motorisé'
            ? Equipement::TYPES_VEHICULES
            : Equipement::TYPES_EQUIPEMENTS;
        return $this->json(array_keys($types));
    }

    private function denyAccessUnlessOwner(Equipement $equipement): void
    {
        if ($equipement->getUserlog() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet équipement.');
        }
    }
}