<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use App\Service\AdminActivityNotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * Listens for user entity lifecycle events and triggers admin notifications.
 * Tracks: new user creation, user deletion, role changes.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
class UserActivityListener
{
    public function __construct(
        private AdminActivityNotificationService $adminNotificationService,
    ) {}

    /**
     * Called after a new user is persisted to database.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        // Don't notify for system/test users
        if ($this->isSystemUser($entity)) {
            return;
        }

        try {
            // Notify admins that a new user joined
            $this->adminNotificationService->notifyNewUserJoined($entity);
        } catch (\Exception $e) {
            // Log error but don't break the flow
            error_log('Admin notification error on user creation: ' . $e->getMessage());
        }
    }

    /**
     * Called after a user is deleted from database.
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        // Don't notify for system/test users
        if ($this->isSystemUser($entity)) {
            return;
        }

        try {
            // Notify admins that a user left
            $this->adminNotificationService->notifyUserLeft($entity);
        } catch (\Exception $e) {
            // Log error but don't break the flow
            error_log('Admin notification error on user deletion: ' . $e->getMessage());
        }
    }

    /**
     * Check if user is a system user (shouldn't trigger notifications).
     */
    private function isSystemUser(User $user): bool
    {
        // Add conditions to identify system users
        // For example: test users, automation accounts, etc.
        return false;
    }
}
