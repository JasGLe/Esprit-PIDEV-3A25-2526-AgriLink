<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Repository\Activity\EvenementRepository;
use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $role = $user->getRole();

        // Redirect to role-specific dashboard
        return match ($role) {
            User::ROLE_ADMIN => $this->redirectToRoute('admin_dashboard'),
            User::ROLE_AGRICULTEUR => $this->redirectToRoute('app_dashboard_agriculteur'),
            User::ROLE_FOURNISSEUR => $this->redirectToRoute('app_dashboard_fournisseur'),
            User::ROLE_AGRIPLUS => $this->redirectToRoute('app_dashboard_agriplus'),
            default => $this->redirectToRoute('app_dashboard_user'),
        };
    }

    #[Route('/dashboard/admin', name: 'app_dashboard_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        // Redirect to new admin dashboard
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/dashboard/agriculteur', name: 'app_dashboard_agriculteur')]
    #[IsGranted('ROLE_AGRICULTEUR')]
    public function agriculteurDashboard(
        EvenementRepository   $evenementRepository,
        EquipementRepository  $equipementRepository,
        MaintenanceRepository $maintenanceRepository
    ): Response {
        /** @var User $user */
        $user   = $this->getUser();
        $userId = (int) $user->getId();

        // ── Équipements de l'utilisateur ────────────────────────────────────
        $equipements   = $equipementRepository->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        // Map id → objet Equipement (pour le template)
        $equipMap = [];
        foreach ($equipements as $eq) {
            $equipMap[$eq->getId()] = $eq;
        }

        // Répartition par statut (Actif, En panne, En maintenance, Hors service)
        $equipementsParStatut = $equipementRepository->countByStatut($userId);

        // Équipements géolocalisés (lat + lng non null) pour la carte Leaflet
        $equipementsGeo = [];
        foreach ($equipements as $eq) {
            if ($eq->getLatitude() !== null && $eq->getLongitude() !== null) {
                $equipementsGeo[] = [
                    'id'     => $eq->getId(),
                    'nom'    => $eq->getNom(),
                    'type'   => $eq->getType(),
                    'statut' => $eq->getStatut(),
                    'lat'    => $eq->getLatitude(),
                    'lng'    => $eq->getLongitude(),
                    'url'    => $this->generateUrl('equipement_show', ['id' => $eq->getId()]),
                ];
            }
        }

        // ── Statistiques maintenances ────────────────────────────────────────
        $totalMaintenances       = $maintenanceRepository->countTotalForUser($equipementIds);
        $maintenancesEnRetard    = $maintenanceRepository->countEnRetardForUser($equipementIds);
        $coutTotalMaintenances   = $maintenanceRepository->sumCoutForUser($equipementIds);
        $prochainesMaintenances  = $maintenanceRepository->findProchainesForUser($equipementIds, 3);

        return $this->render('user_management/dashboard/agriculteur.html.twig', [
            // Événements (existants)
            'myEvenementsCount' => $evenementRepository->countByOrganisateurId($userId),
            'myEvenements'      => $evenementRepository->findLatestByOrganisateurId($userId, 6),
            // Équipements
            'totalEquipements'    => count($equipements),
            'equipementsParStatut' => $equipementsParStatut,
            'equipementsGeo'      => $equipementsGeo,
            'equipMap'            => $equipMap,
            // Maintenances
            'totalMaintenances'      => $totalMaintenances,
            'maintenancesEnRetard'   => $maintenancesEnRetard,
            'coutTotalMaintenances'  => $coutTotalMaintenances,
            'prochainesMaintenances' => $prochainesMaintenances,
        ]);
    }

    #[Route('/dashboard/fournisseur', name: 'app_dashboard_fournisseur')]
    #[IsGranted('ROLE_FOURNISSEUR')]
    public function fournisseurDashboard(): Response
    {
        return $this->render('user_management/dashboard/fournisseur.html.twig');
    }

    #[Route('/dashboard/agriplus', name: 'app_dashboard_agriplus')]
    #[IsGranted('ROLE_AGRIPLUS')]
    public function agriplusDashboard(): Response
    {
        return $this->render('user_management/dashboard/agriplus.html.twig');
    }

    #[Route('/dashboard/user', name: 'app_dashboard_user')]
    public function userDashboard(): Response
    {
        return $this->render('user_management/dashboard/user.html.twig');
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('user_management/home/landing.html.twig');
    }
}
