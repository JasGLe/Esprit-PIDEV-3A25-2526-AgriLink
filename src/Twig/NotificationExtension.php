<?php
namespace App\Twig;


use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use App\Repository\NotificationsRepository;

class NotificationExtension extends AbstractExtension
    implements GlobalsInterface
{
    public function __construct(
        private NotificationsRepository $repo,
        private Security               $security
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            return ['notif_count' => 0, 'notifs_recentes' => []];
        }

        return [
            'notif_count'     => $this->repo->countUnreadByUser($user),
            'notifs_recentes' => $this->repo->findRecentByUser($user, 5),
        ];
    }
}