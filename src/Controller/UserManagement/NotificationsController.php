<?php

namespace App\Controller\UserManagement;

use App\Entity\Notifications;
use App\Entity\UserManagement\User;
use App\Repository\NotificationsRepository;
use App\Service\AdminActivityNotificationService;
use App\Service\OneSignalPushService;
use App\Service\UserProfileNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'app_notifications_')]
#[IsGranted('ROLE_USER')]
class NotificationsController extends AbstractController
{
    public function __construct(
        private NotificationsRepository $notificationsRepository,
        private OneSignalPushService $oneSignalPushService,
        private UserProfileNotificationService $profileNotificationService,
        private AdminActivityNotificationService $adminActivityNotificationService,
    ) {}

    /**
     * Get all unread notifications for current user
     */
    #[Route('/unread', name: 'unread', methods: ['GET'])]
    public function getUnread(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationsRepository->findUnreadByUserId($user->getId(), limit: 20);

        return $this->json([
            'count' => count($notifications),
            'notifications' => array_map(fn($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'title' => $n->getTitle(),
                'body' => $n->getBody(),
                'createdAt' => $n->getCreatedAt()->format('c'),
            ], $notifications),
        ]);
    }

    /**
     * Mark notification as read
     */
    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $notification = $this->notificationsRepository->find($id);

        if (!$notification || $notification->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $notification->setReadAt(new \DateTime());
        $this->notificationsRepository->save($notification, flush: true);

        return $this->json(['success' => true]);
    }

    /**
     * Delete a notification
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $notification = $this->notificationsRepository->find($id);

        if (!$notification || $notification->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->notificationsRepository->remove($notification, flush: true);

        return $this->json(['success' => true]);
    }

    /**
     * Get profile completion score and contextual suggestions
     */
    #[Route('/profile-status', name: 'profile_status', methods: ['GET'])]
    public function getProfileStatus(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $completionScore = $this->profileNotificationService->getProfileCompletionScore($user);
        $contextualNotifications = $this->profileNotificationService->generateContextualNotifications($user);

        return $this->json([
            'completionScore' => $completionScore,
            'suggestions' => $contextualNotifications,
            'completed' => $completionScore === 100,
        ]);
    }

    /**
     * Get admin activity summary (admin only)
     */
    #[Route('/admin/activity-summary', name: 'admin_activity_summary', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getAdminActivitySummary(): JsonResponse
    {
        $summary = $this->adminActivityNotificationService->getActivitySummary();

        return $this->json([
            'summary' => $summary,
            'timestamp' => (new \DateTime())->format('c'),
        ]);
    }

    /**
     * Get admin notifications with activity type filtering
     */
    #[Route('/admin/activity', name: 'admin_activity', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getAdminActivity(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get activity notifications (user_joined, user_left, role_changed, etc.)
        $activityTypes = [
            'user_joined',
            'user_left',
            'role_changed',
            'user_deactivated',
            'user_reactivated',
            'bulk_operation',
        ];

        $qb = $this->notificationsRepository->createQueryBuilder('n');
        $notifications = $qb
            ->where('n.userId = :userId')
            ->andWhere($qb->expr()->in('n.type', ':types'))
            ->setParameter('userId', $user->getId())
            ->setParameter('types', $activityTypes)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($notifications),
            'notifications' => array_map(fn($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'title' => $n->getTitle(),
                'body' => $n->getBody(),
                'createdAt' => $n->getCreatedAt()->format('c'),
                'isRead' => $n->getReadAt() !== null,
            ], $notifications),
        ]);
    }

    /**
     * Get notification preferences / dismissal status
     */
    #[Route('/preferences', name: 'preferences', methods: ['GET', 'POST'])]
    public function preferences(): Response
    {
        return $this->json(['message' => 'Preferences endpoint']);
    }

    #[Route('/test-push', name: 'test_push', methods: ['POST'])]
    public function testPush(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = (int) ($user->getId() ?? 0);
        $displayName = trim((string) ($user->getDisplayName() ?? $user->getEmail() ?? 'Utilisateur'));

        $title = 'Test notification OneSignal';
        $body = sprintf('Bonjour %s, ceci est un test push + in-app.', $displayName);

        $notif = new Notifications();
        $notif->setUserId($userId);
        $notif->setType('test_push');
        $notif->setTitle('🔔 '.$title);
        $notif->setBody($body);
        $notif->setCreatedAt(new \DateTimeImmutable());
        $this->notificationsRepository->save($notif, flush: true);

        $pushResult = $this->oneSignalPushService->sendToUserIds(
            [$userId],
            $title,
            $body,
            [
                'type' => 'test_push',
                'targetRole' => (string) $user->getRole(),
                'userId' => (string) $userId,
            ]
        );

        return $this->json([
            'success' => true,
            'message' => 'Test notification envoyee (in-app + OneSignal).',
            'userId' => $userId,
            'push' => $pushResult,
        ]);
    }

    /**
     * Notification center page - manage all notifications
     */
    #[Route('', name: 'center', methods: ['GET'])]
    public function center(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get all notifications (not just unread)
        $notifications = $this->notificationsRepository->createQueryBuilder('n')
            ->where('n.userId = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('user_management/notifications_center.html.twig', [
            'notifications' => $notifications,
            'notificationCount' => count($notifications),
        ]);
    }
}

