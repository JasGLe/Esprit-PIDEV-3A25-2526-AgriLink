<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;

class AuthenticationListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationEvent::class => 'onAuthenticationSuccess',
            // Only listen to kernel request to catch authentication failure redirects
            KernelEvents::REQUEST => ['onKernelRequest', 9],  // High priority
        ];
    }

    /**
     * Handle kernel request to catch auth failures
     * NOTE: This method is deprecated - LoginFailureListener now handles failed attempts
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Only handle main requests, not sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only process POST requests to login
        if ($request->getPathInfo() !== '/login' || !$request->isMethod('POST')) {
            return;
        }

        // Note: Failed login attempts are now handled by LoginFailureListener
        // This method is kept for potential future use (e.g., session management)
    }

    /**
     * Handle successful authentication
     */
    public function onAuthenticationSuccess(AuthenticationEvent $event): void
    {
        $token = $event->getAuthenticationToken();
        $user = $token->getUser();

        if ($user instanceof User) {
            // Reset failed login attempts on successful login
            $user->setFailedLoginAttempts(0);
            $this->entityManager->flush($user);
        }
    }
}


