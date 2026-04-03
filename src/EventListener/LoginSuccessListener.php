<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecurityEventService $securityEventService
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

        // Log successful login
        $this->securityEventService->logLoginSuccess($user);
    }
}
