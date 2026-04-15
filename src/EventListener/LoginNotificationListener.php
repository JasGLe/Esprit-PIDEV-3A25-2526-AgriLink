<?php

namespace App\EventListener;

use App\Service\UserProfileNotificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Listens for successful login and generates welcome + contextual notifications.
 * Only triggers on actual dashboard visit, not during post-registration auto-login.
 */
#[AsEventListener(event: InteractiveLoginEvent::class)]
class LoginNotificationListener
{
    public function __construct(
        private UserProfileNotificationService $notificationService,
        private RequestStack $requestStack,
    ) {}

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $user = $event->getAuthenticationToken()->getUser();

        // Skip notifications during post-registration auto-login
        // Only trigger on actual dashboard visit
        if ($session->get('skip_login_notifications')) {
            $session->remove('skip_login_notifications');
            return;
        }

        // Skip notifications for admins - they only receive admin activity notifications
        if ($user->isAdmin()) {
            return;
        }

        // Create welcome notification
        $this->notificationService->createWelcomeNotification($user);

        // Generate contextual notifications
        $contextualNotifications = $this->notificationService->generateContextualNotifications($user);

        // Persist them
        if (!empty($contextualNotifications)) {
            $this->notificationService->persistNotifications($user, $contextualNotifications);
        }
    }
}
