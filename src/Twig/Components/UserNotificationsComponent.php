<?php

namespace App\Twig\Components;

use App\Entity\UserManagement\User;
use App\Repository\NotificationsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Twig component to display user notifications with actions.
 * Handles dismissing, action clicks, and priority-based sorting.
 */
class UserNotificationsComponent extends AbstractController
{
    public function __construct(
        private NotificationsRepository $notificationsRepository,
    ) {}

    public function getNotifications(): array
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return [];
        }
        $userId = $user->getId();
        if ($userId === null) {
            return [];
        }

        // Get unread notifications
        $notifications = $this->notificationsRepository->findUnreadByUserId(
            $userId,
            limit: 10
        );

        // Map to readable format
        return array_map(fn($n) => $this->mapNotification($n), $notifications);
    }

    #[LiveAction]
    public function dismissNotification(int $notificationId): void
    {
        $notification = $this->notificationsRepository->find($notificationId);
        if ($notification && $notification->getUserId() === $this->getUser()?->getId()) {
            $notification->markAsRead();
            $this->notificationsRepository->save($notification, flush: true);
        }
    }

    private function mapNotification($notif): array
    {
        return [
            'id' => $notif->getId(),
            'type' => $notif->getType(),
            'title' => $notif->getTitle(),
            'body' => $notif->getBody(),
            'createdAt' => $notif->getCreatedAt(),
            'icon' => $this->getIconForType($notif->getType()),
            'priority' => $this->getPriorityForType($notif->getType()),
            'actionUrl' => $this->getActionUrlForType($notif->getType()),
            'actionLabel' => $this->getActionLabelForType($notif->getType()),
        ];
    }

    private function getIconForType(string $type): string
    {
        return match ($type) {
            'order_cancellation_requested_admin' => '⚠️',
            'order_created_admin' => '📦',
            'order_status_changed' => '🔄',
            'marketplace_product_submitted' => '🛍️',
            'email_verification_pending' => '📧',
            'phone_verification_pending' => '📱',
            '2fa_setup_pending' => '🔐',
            'profile_completion_pending' => '👤',
            'user_welcome' => '🌟',
            default => 'ℹ️',
        };
    }

    private function getPriorityForType(string $type): string
    {
        return match ($type) {
            'order_cancellation_requested_admin' => 'high',
            'order_created_admin', 'order_status_changed', 'marketplace_product_submitted' => 'medium',
            'email_verification_pending', '2fa_setup_pending' => 'high',
            'phone_verification_pending' => 'medium',
            'profile_completion_pending' => 'low',
            'user_welcome' => 'info',
            default => 'medium',
        };
    }

    private function getActionUrlForType(string $type): ?string
    {
        return match ($type) {
            'order_cancellation_requested_admin', 'order_created_admin', 'order_status_changed' => '/mes-commandes',
            'marketplace_product_submitted' => '/admin/moderation/boutique',
            'email_verification_pending' => '/verify-email',
            'phone_verification_pending' => '/verify-phone',
            '2fa_setup_pending' => '/2fa/setup',
            'profile_completion_pending' => '/profile/edit',
            default => null,
        };
    }

    private function getActionLabelForType(string $type): string
    {
        return match ($type) {
            'order_cancellation_requested_admin' => 'Voir la demande',
            'order_created_admin', 'order_status_changed' => 'Voir les commandes',
            'marketplace_product_submitted' => 'Ouvrir modération',
            'email_verification_pending' => 'Vérifier maintenant',
            'phone_verification_pending' => 'Ajouter téléphone',
            '2fa_setup_pending' => 'Activer 2FA',
            'profile_completion_pending' => 'Compléter profil',
            'user_welcome' => 'Aller au tableau de bord',
            default => 'En savoir plus',
        };
    }
}
