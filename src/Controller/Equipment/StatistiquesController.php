<?php

namespace App\Controller\Equipment;

use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/equipement/statistiques', name: 'equipement_stats_')]
class StatistiquesController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        EquipementRepository  $equipRepo,
        MaintenanceRepository $mainRepo
    ): Response {
        $userId        = $this->getUser()->getId();
        $equipements   = $equipRepo->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        $stats = $this->buildStats($userId, $equipementIds, $equipRepo, $mainRepo);

        return $this->render('equipment/statistiques/index.html.twig', $stats);
    }

    private function buildStats(
        int $userId,
        array $equipementIds,
        EquipementRepository $equipRepo,
        MaintenanceRepository $mainRepo
    ): array {
        $equipParStatut      = $equipRepo->countByStatut($userId);
        $equipParCategorie   = $equipRepo->countByCategorie($userId);
        $equipParType        = $equipRepo->countByType($userId);
        $mainParStatut       = $mainRepo->countByStatutForUser($equipementIds);
        $mainParType         = $mainRepo->countByTypeForUser($equipementIds);
        $mainParMois         = $mainRepo->countByMoisForUser($equipementIds);

        return [
            // Équipements
            'totalEquipements'         => count($equipementIds),
            'equipParStatut'           => $equipParStatut,
            'equipParStatutLabels'     => array_keys($equipParStatut),
            'equipParStatutValeurs'    => array_values($equipParStatut),
            'equipParCategorie'        => $equipParCategorie,
            'equipParCategorieLabels'  => array_keys($equipParCategorie),
            'equipParCategorieValeurs' => array_values($equipParCategorie),
            'equipParType'             => $equipParType,
            'equipParTypeLabels'       => array_keys($equipParType),
            'equipParTypeValeurs'      => array_values($equipParType),

            // Maintenances
            'totalMaintenances'        => array_sum($mainParStatut),
            'mainParStatut'            => $mainParStatut,
            'mainParStatutLabels'      => array_keys($mainParStatut),
            'mainParStatutValeurs'     => array_values($mainParStatut),
            'mainParType'              => $mainParType,
            'mainParTypeLabels'        => array_keys($mainParType),
            'mainParTypeValeurs'       => array_values($mainParType),
            'mainParMois'              => $mainParMois,
            'mainParMoisLabels'        => array_keys($mainParMois),
            'mainParMoisValeurs'       => array_values($mainParMois),
            'mainEnRetard'             => $mainRepo->countEnRetardForUser($equipementIds),
        ];
    }
}