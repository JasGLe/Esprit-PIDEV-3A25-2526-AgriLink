<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use App\Entity\UserManagement\UserSession;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecurityEventService $securityEventService,
        private RequestStack $requestStack
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Reset failed login attempts
        $user->resetFailedLoginAttempts();

        // Clear locked_until
        $user->setLockedUntil(null);

        // Set last login timestamp
        $user->setLastLogin(new \DateTime());

        // Persist changes
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Create UserSession record for all login types (traditional, OAuth, etc)
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $userSession = new UserSession();
            $userSession->setUser($user);
            $userSession->setDeviceInfo($request->headers->get('User-Agent'));
            $userSession->setRefreshTokenHash(hash('sha256', (string)$request->getSession()->getId()));
            $userSession->setExpiresAt(new \DateTime('+30 days')); // 30-day session

            $this->entityManager->persist($userSession);
            $this->entityManager->flush();
        }

        // Log successful login
        $this->securityEventService->logLoginSuccess($user);
    }
}

