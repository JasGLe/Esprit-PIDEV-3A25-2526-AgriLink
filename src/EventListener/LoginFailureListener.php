<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use App\Repository\UserManagement\UserRepository;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginFailureListener
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_DURATION_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SecurityEventService $securityEventService
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        
        // Get email from the login form request
        $email = $request->request->get('_username');
        
        if (!$email) {
            // Log failed login with no email provided
            $this->securityEventService->logLoginFailed(null, 'No email provided');
            return;
        }

        // Find user by email
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            // Log failed login for unknown user
            $this->securityEventService->logLoginFailed($email, 'User not found');
            return;
        }

        // Increment failed login attempts
        $user->incrementFailedLoginAttempts();

        // Lock account if max attempts reached
        $accountLocked = false;
        if ($user->getFailedLoginAttempts() >= self::MAX_FAILED_ATTEMPTS) {
            $lockedUntil = new \DateTime();
            $lockedUntil->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes');
            $user->setLockedUntil($lockedUntil);
            $accountLocked = true;
        }

        // Persist changes
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Log failed login attempt
        $this->securityEventService->logLoginFailed($email, 'Invalid credentials');

        // Log account locked if applicable
        if ($accountLocked) {
            $this->securityEventService->logAccountLocked($user);
        }
    }
}
