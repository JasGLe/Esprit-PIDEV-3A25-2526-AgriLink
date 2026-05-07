<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use App\Entity\Notifications;
use App\Repository\NotificationsRepository;
use App\Repository\UserManagement\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for generating and managing admin notifications about user activity.
 * Tracks: new user registrations, user deletions, role changes, status updates.
 */
class AdminActivityNotificationService
{
    public function __construct(
        private NotificationsRepository $notificationsRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Notify admins when a new user joins the system.
     */
    public function notifyNewUserJoined(User $newUser): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('user_joined');
            $notification->setTitle('👤 Nouvel utilisateur');
            $notification->setBody($this->buildNewUserBody($newUser));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Notify admins when a user account is deleted.
     */
    public function notifyUserLeft(User $deletedUser): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('user_left');
            $notification->setTitle('👋 Utilisateur supprimé');
            $notification->setBody($this->buildUserLeftBody($deletedUser));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Notify admins when a user's role changes.
     */
    public function notifyRoleChanged(User $user, string $oldRole, string $newRole): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('role_changed');
            $notification->setTitle('🔄 Rôle modifié');
            $notification->setBody($this->buildRoleChangeBody($user, $oldRole, $newRole));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Notify admins when a user is deactivated.
     */
    public function notifyUserDeactivated(User $user, string $reason = ''): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('user_deactivated');
            $notification->setTitle('🚫 Utilisateur désactivé');
            $notification->setBody($this->buildUserDeactivatedBody($user, $reason));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Notify admins when a user is reactivated.
     */
    public function notifyUserReactivated(User $user): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('user_reactivated');
            $notification->setTitle('✅ Utilisateur réactivé');
            $notification->setBody($this->buildUserReactivatedBody($user));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Notify admins of bulk user operations.
     */
    public function notifyBulkOperation(string $operationType, int $count, string $details = ''): void
    {
        $admins = $this->userRepository->findByRole('ADMIN');

        $titles = [
            'import' => '📥 Importation utilisateurs',
            'export' => '📤 Exportation utilisateurs',
            'delete' => '🗑️ Suppression en masse',
            'activate' => '✅ Activation en masse',
            'deactivate' => '🚫 Désactivation en masse',
        ];

        $title = $titles[$operationType] ?? '⚙️ Opération en masse';

        foreach ($admins as $admin) {
            $notification = new Notifications();
            $notification->setUser($admin);
            $notification->setType('bulk_operation');
            $notification->setTitle($title);
            $notification->setBody($this->buildBulkOperationBody($operationType, $count, $details));

            $this->notificationsRepository->save($notification, flush: false);
        }

        $this->entityManager->flush();
    }

    /**
     * Get admin activity summary for dashboard.
     *
     * @return array{newUsersLast24h:int,totalUsers:int,activeUsers:int,inactiveUsers:int,timestamp:\DateTimeInterface}
     */
    public function getActivitySummary(): array
    {
        // Get counts from last 24 hours
        $date24HoursAgo = new \DateTime('-24 hours');

        $qb = $this->entityManager->createQueryBuilder();
        $newUsersCount = $qb
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $date24HoursAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $totalUsers = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        $activeUsers = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'newUsersLast24h' => (int) $newUsersCount,
            'totalUsers' => (int) $totalUsers,
            'activeUsers' => (int) $activeUsers,
            'inactiveUsers' => (int) $totalUsers - (int) $activeUsers,
            'timestamp' => new \DateTime(),
        ];
    }

    // ========== PRIVATE HELPERS ==========

    private function buildNewUserBody(User $newUser): string
    {
        $email = (string) ($newUser->getEmail() ?? '');
        $role = $this->formatRole($newUser->getRole());
        $date = $newUser->getCreatedAt()->format('d/m/Y H:i');

        return "📧 $email\n🔑 Rôle: $role\n📅 $date";
    }

    private function buildUserLeftBody(User $deletedUser): string
    {
        $email = $deletedUser->getEmail();
        $role = $this->formatRole($deletedUser->getRole());

        return "📧 $email\n🔑 Rôle: $role\n⏰ Compte supprimé";
    }

    private function buildRoleChangeBody(User $user, string $oldRole, string $newRole): string
    {
        $email = $user->getEmail();
        $old = $this->formatRole($oldRole);
        $new = $this->formatRole($newRole);

        return "📧 $email\n🔄 $old → $new";
    }

    private function buildUserDeactivatedBody(User $user, string $reason = ''): string
    {
        $email = $user->getEmail();
        $reasonText = $reason ? "\n📝 Raison: $reason" : '';

        return "📧 $email$reasonText\n⏸️ Compte désactivé";
    }

    private function buildUserReactivatedBody(User $user): string
    {
        $email = $user->getEmail();

        return "📧 $email\n▶️ Compte réactivé";
    }

    private function buildBulkOperationBody(string $operationType, int $count, string $details = ''): string
    {
        $actions = [
            'import' => 'importés',
            'export' => 'exportés',
            'delete' => 'supprimés',
            'activate' => 'activés',
            'deactivate' => 'désactivés',
        ];

        $action = $actions[$operationType] ?? 'traités';
        $body = "📊 $count utilisateurs $action";

        if ($details) {
            $body .= "\n📝 $details";
        }

        return $body;
    }

    private function formatRole(string $role): string
    {
        $roles = [
            'ROLE_ADMIN' => 'Administrateur',
            'ROLE_AGRICULTEUR' => 'Agriculteur',
            'ROLE_FOURNISSEUR' => 'Fournisseur',
            'ROLE_AGRIPLUS' => 'AgriPlus',
            'ROLE_USER' => 'Utilisateur',
        ];

        return $roles[$role] ?? $role;
    }
}
