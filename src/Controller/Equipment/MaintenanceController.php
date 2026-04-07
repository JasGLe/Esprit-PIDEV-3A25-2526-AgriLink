<?php

namespace App\Controller\Equipment;

use App\Entity\Maintenance;
use App\Form\Equipment\MaintenanceType;
use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/maintenance', name: 'maintenance_')]
class MaintenanceController extends AbstractController
{
    // ════════════════════════════════════════════════════════
    // INDEX — toutes les maintenances de l'agriculteur
    // ════════════════════════════════════════════════════════
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        MaintenanceRepository $repo,
        EquipementRepository  $equipRepo,
        Request $request
    ): Response {
        $userId = $this->getUser()->getId();

        // Récupérer les IDs des équipements de l'agriculteur
        $equipements   = $equipRepo->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        // Toutes les maintenances de ces équipements
        $maintenances = $repo->findByEquipementIds($equipementIds);

        // Map id => équipement pour affichage dans la liste
        $equipMap = [];
        foreach ($equipements as $eq) {
            $equipMap[$eq->getId()] = $eq;
        }

        return $this->render('equipment/maintenance/index.html.twig', [
            'maintenances' => $maintenances,
            'equipMap'     => $equipMap,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // NEW
    // ════════════════════════════════════════════════════════
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Route('/new/{equipementId}', name: 'new_for_equipement', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        int $equipementId = 0
    ): Response {
        $maintenance = new Maintenance();

        // Pré-remplir l'équipement si on vient depuis la fiche équipement
        if ($equipementId > 0) {
            $maintenance->setEquipementId($equipementId);
        }

        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $this->getUser()->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maintenance->setUserlog($this->getUser()->getId());
            $em->persist($maintenance);
            $em->flush();

            $this->addFlash('success', 'Maintenance planifiée avec succès !');

            // Rediriger vers la fiche équipement si on venait de là
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

    // ════════════════════════════════════════════════════════
    // SHOW
    // ════════════════════════════════════════════════════════
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Maintenance $maintenance,
        EquipementRepository $equipRepo
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        $equipement = $equipRepo->find($maintenance->getEquipementId());

        return $this->render('equipment/maintenance/show.html.twig', [
            'maintenance' => $maintenance,
            'equipement'  => $equipement,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // EDIT
    // ════════════════════════════════════════════════════════
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Maintenance $maintenance,
        EntityManagerInterface $em,
        EquipementRepository $equipRepo
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $this->getUser()->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Maintenance modifiée avec succès !');
            return $this->redirectToRoute('maintenance_show', ['id' => $maintenance->getId()]);
        }

        return $this->render('equipment/maintenance/edit.html.twig', [
            'form'        => $form,
            'maintenance' => $maintenance,
            'equipement'  => $equipRepo->find($maintenance->getEquipementId()),
        ]);
    }

    // ════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Maintenance $maintenance,
        EntityManagerInterface $em,
        EquipementRepository $equipRepo
    ): Response {
        $this->denyAccessUnlessOwner($maintenance, $equipRepo);

        $equipementId = $maintenance->getEquipementId();

        if ($this->isCsrfTokenValid('delete' . $maintenance->getId(), $request->request->get('_token'))) {
            $em->remove($maintenance);
            $em->flush();
            $this->addFlash('success', 'Maintenance supprimée.');
        }

        return $this->redirectToRoute('maintenance_index');
    }

    // ════════════════════════════════════════════════════════
    // Sécurité : l'agriculteur ne touche que ses maintenances
    // ════════════════════════════════════════════════════════
    private function denyAccessUnlessOwner(
        Maintenance $maintenance,
        EquipementRepository $equipRepo
    ): void {
        $equipement = $equipRepo->find($maintenance->getEquipementId());
        if (!$equipement || $equipement->getUserlog() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException(
                'Vous n\'avez pas accès à cette maintenance.'
            );
        }
    }
}