<?php

namespace App\Controller\UserManagement\Admin;

use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SecurityEventRepository $securityEventRepository
    ) {}

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        // Get user statistics
        $stats = $this->userRepository->getStatistics();
        
        // Get recent registrations (last 7 days)
        $recentUsers = $this->userRepository->findRecentUsers(7, 10);
        
        // Get security events summary
        $securityStats = $this->securityEventRepository->getStatistics(30); // last 30 days
        
        // Get recent security events
        $recentEvents = $this->securityEventRepository->findRecent(15);
        
        // Calculate growth rates
        $previousPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-60 days'),
            new \DateTime('-30 days')
        );
        
        $currentPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-30 days'),
            new \DateTime('now')
        );
        
        $growthRate = $this->calculateGrowthRate(
            $previousPeriodStats['newUsers'] ?? 0,
            $currentPeriodStats['newUsers'] ?? 0
        );

        return $this->render('user_management/dashboard/admin.html.twig', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'securityStats' => $securityStats,
            'recentEvents' => $recentEvents,
            'growthRate' => $growthRate,
            'currentPeriodStats' => $currentPeriodStats,
        ]);
    }

    private function calculateGrowthRate(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }
}
