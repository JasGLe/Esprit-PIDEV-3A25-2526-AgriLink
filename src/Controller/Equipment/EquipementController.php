<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Form\Equipment\EquipementType;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/equipement', name: 'equipement_')]
class EquipementController extends AbstractController
{
    // ════════════════════════════════════════════════════════
    // INDEX — liste tous les équipements de l'agriculteur
    // ════════════════════════════════════════════════════════
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EquipementRepository $repo): Response
    {
        $user = $this->getUser();

        $equipements = $repo->findBy(
            ['userlog' => $user->getId()],
            ['dateCreation' => 'DESC']
        );

        return $this->render('equipment/index.html.twig', [
            'equipements' => $equipements,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // NEW — formulaire d'ajout
    // ════════════════════════════════════════════════════════
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Upload image ─────────────────────────────────────────────────
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo(
                    $imageFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename  = $safeFilename . '_' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('equipements_directory'),
                        $newFilename
                    );
                    $equipement->setImageUrl('uploads/equipements/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            // ── Nettoyer les champs véhicule si pas un véhicule ──────────────
            // Même si le JS les masque, on s'assure côté serveur qu'ils sont null
            if (!$equipement->isVehicule()) {
                $equipement
                    ->setKilometrageActuel(null)
                    ->setKilometrageDerniereMaintenance(null)
                    ->setSeuilKmMaintenance(null)
                    ->setHeuresUtilisation(null)
                    ->setHeuresDerniereMaintenance(null)
                    ->setSeuilHeuresMaintenance(null);
            }

            // ── Lier à l'utilisateur connecté ───────────────────────────────
            $equipement->setUserlog($this->getUser()->getId());

            $em->persist($equipement);
            $em->flush();

            $this->addFlash('success', 'Équipement ajouté avec succès !');
            return $this->redirectToRoute('equipement_index');
        }

        return $this->render('equipment/new.html.twig', [
            'form'       => $form,
            'equipement' => $equipement,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // SHOW — fiche détail
    // ════════════════════════════════════════════════════════
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Equipement $equipement): Response
    {
        $this->denyAccessUnlessOwner($equipement);

        return $this->render('equipment/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // EDIT — formulaire de modification
    // ════════════════════════════════════════════════════════
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessOwner($equipement);

        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Upload image (optionnel en édition) ──────────────────────────
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo(
                    $imageFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename  = $safeFilename . '_' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('equipements_directory'),
                        $newFilename
                    );
                    $equipement->setImageUrl('uploads/equipements/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            // ── Nettoyer les champs véhicule si catégorie changée ────────────
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

            $this->addFlash('success', 'Équipement modifié avec succès !');
            return $this->redirectToRoute('equipement_index');
        }

        return $this->render('equipment/edit.html.twig', [
            'form'       => $form,
            'equipement' => $equipement,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // DELETE — suppression avec token CSRF
    // ════════════════════════════════════════════════════════
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

    // ════════════════════════════════════════════════════════
    // API AJAX — retourne les types selon la catégorie
    // Appelé par le JS du formulaire pour peupler le select 'type'
    // ════════════════════════════════════════════════════════
    #[Route('/api/types', name: 'api_types', methods: ['GET'])]
    public function apiTypes(Request $request): JsonResponse
    {
        $categorie = $request->query->get('categorie', 'Autre Équipement');

        $types = $categorie === 'Véhicule Motorisé'
            ? Equipement::TYPES_VEHICULES
            : Equipement::TYPES_EQUIPEMENTS;

        return $this->json(array_keys($types));
    }

    // ════════════════════════════════════════════════════════
    // Helper privé — sécurité : l'agriculteur ne voit que ses équipements
    // ════════════════════════════════════════════════════════
    private function denyAccessUnlessOwner(Equipement $equipement): void
    {
        if ($equipement->getUserlog() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException(
                'Vous n\'avez pas accès à cet équipement.'
            );
        }
    }
}