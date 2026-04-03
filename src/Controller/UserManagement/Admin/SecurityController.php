<?php

namespace App\Controller\UserManagement\Admin;

use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/security', name: 'admin_security_')]
#[IsGranted('ROLE_ADMIN')]
class SecurityController extends AbstractController
{
    public function __construct(
        private SecurityEventRepository $securityEventRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function securityDashboard(Request $request): Response
    {
        // Security statistics for different periods
        $stats24h = $this->securityEventRepository->getStatistics(1);
        $stats7d = $this->securityEventRepository->getStatistics(7);
        $stats30d = $this->securityEventRepository->getStatistics(30);

        // Users with security concerns
        $lockedAccounts = $this->userRepository->findLockedAccounts();
        $failedLoginUsers = $this->userRepository->findUsersWithFailedLogins(5);
        $unverifiedAccounts = $this->userRepository->findUnverifiedAccounts(30);

        // Recent security events (last 20)
        $recentEvents = $this->securityEventRepository->findRecent(20);

        // Account status overview
        $accountStats = $this->userRepository->getAccountSecurityStats();

        return $this->render('user_management/admin/security/logs.html.twig', [
            'stats24h' => $stats24h,
            'stats7d' => $stats7d,
            'stats30d' => $stats30d,
            'lockedAccounts' => $lockedAccounts,
            'failedLoginUsers' => $failedLoginUsers,
            'unverifiedAccounts' => $unverifiedAccounts,
            'recentEvents' => $recentEvents,
            'accountStats' => $accountStats,
        ]);
    }
}
