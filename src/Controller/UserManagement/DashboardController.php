<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Repository\Activity\ActiviteRepository;
use App\Repository\Activity\EvenementRepository;
use App\Service\OpenWeatherMapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly OpenWeatherMapService $openWeatherMapService,
    ) {
    }

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
        EvenementRepository $evenementRepository,
        ActiviteRepository $activiteRepository,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userCity = $user->getVille();
        $weather = $this->openWeatherMapService->getCurrentWeatherForCity($userCity);
        $events = [];

        $todayStart = new \DateTimeImmutable('today 00:00:00');
        $todayEnd = new \DateTimeImmutable('today 23:59:59');

        $todayActivites = $activiteRepository->findBetweenDates($todayStart, $todayEnd);
        foreach ($todayActivites as $activite) {
            if ($activite->getIdAgriculteur() !== $user->getId()) {
                continue;
            }

            $start = $activite->getDateDebut();
            $events[] = [
                'type' => 'ACTIVITY',
                'typeLabel' => 'Activité',
                'title' => $activite->getTitre(),
                'time' => $start?->format('H:i'),
                'sortKey' => $start?->getTimestamp() ?? PHP_INT_MAX,
            ];
        }

        $todayEvenements = $evenementRepository->findBetweenDates($todayStart, $todayEnd);
        foreach ($todayEvenements as $evenement) {
            if ($evenement->getOrganisateur()?->getId() !== $user->getId()) {
                continue;
            }

            $eventDate = $evenement->getDateEvenement();
            $events[] = [
                'type' => 'EVENT',
                'typeLabel' => 'Événement',
                'title' => $evenement->getTitre(),
                'time' => $eventDate?->format('H:i'),
                'sortKey' => $eventDate?->getTimestamp() ?? PHP_INT_MAX,
            ];
        }

        usort(
            $events,
            static fn (array $a, array $b): int => $a['sortKey'] <=> $b['sortKey']
        );

        return $this->render('user_management/dashboard/agriculteur.html.twig', [
            'myEvenementsCount' => $evenementRepository->countByOrganisateurId((int) $user->getId()),
            'myEvenements' => $evenementRepository->findLatestByOrganisateurId((int) $user->getId(), 6),
            'temperature' => $weather['temperature'] ?? null,
            'weatherIcon' => $weather['icon'] ?? null,
            'weatherDescription' => $weather['description'] ?? null,
            'userCity' => $userCity,
            'events' => $events,
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
