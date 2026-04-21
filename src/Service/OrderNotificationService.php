<?php

namespace App\Service;

use App\Entity\Marketplace\Commandes;
use App\Entity\Notifications;
use App\Entity\UserManagement\User;
use App\Repository\NotificationsRepository;
use App\Repository\UserManagement\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OrderNotificationService
{
    public function __construct(
        private readonly NotificationsRepository $notificationsRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly OneSignalPushService $oneSignalPushService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param int[] $sellerUserIds
     */
    public function notifyOrderCreated(Commandes $commande, User $buyer, array $sellerUserIds, string $buyerDisplayName = ''): void
    {
        $sellerUserIds = array_values(array_unique(array_filter(array_map('intval', $sellerUserIds), static fn (int $v) => $v > 0)));

        /** @var User[] $sellerUsers */
        $sellerUsers = $sellerUserIds !== [] ? $this->userRepository->findBy(['id' => $sellerUserIds]) : [];
        $sellerById = [];
        foreach ($sellerUsers as $sellerUser) {
            $sellerById[(int) $sellerUser->getId()] = $sellerUser;
        }

        $buyerLabel = trim($buyerDisplayName) !== '' ? trim($buyerDisplayName) : ((string) ($buyer->getDisplayName() ?? $buyer->getEmail()));
        $orderRef = $this->formatOrderRef($commande);
        $total = number_format((float) $commande->getPrixTotal(), 3, ',', ' ');

        // Notify each responsible seller/farmer.
        foreach ($sellerUserIds as $sellerUserId) {
            $seller = $sellerById[$sellerUserId] ?? null;
            if (!$seller instanceof User) {
                continue;
            }

            $notif = new Notifications();
            $notif->setUserId($sellerUserId);
            $notif->setType('order_received');
            $notif->setTitle('🛒 Nouvelle commande reçue');
            $notif->setBody(sprintf(
                'Commande %s reçue. Client: %s. Montant total: %s DT.',
                $orderRef,
                $buyerLabel,
                $total
            ));
            $notif->setCommandeId($commande->getId());
            $notif->setCreatedAt(new \DateTimeImmutable());
            $this->notificationsRepository->save($notif, flush: false);
        }

        if ($sellerUserIds !== []) {
            $sellerPushResult = $this->oneSignalPushService->sendToUserIds(
                $sellerUserIds,
                'Nouvelle commande reçue',
                sprintf('Commande %s reçue. Montant %s DT.', $orderRef, $total),
                [
                    'type' => 'order_received',
                    'orderRef' => $orderRef,
                    'commandeId' => (string) ($commande->getId() ?? 0),
                    'targetRole' => 'seller',
                ]
            );
            if (($sellerPushResult['ok'] ?? false) !== true) {
                $this->logger->warning('Seller OneSignal push failed', [
                    'commandeId' => $commande->getId(),
                    'orderRef' => $orderRef,
                    'result' => $sellerPushResult,
                ]);
            }
        }

        // Notify all admins with responsible parties list.
        $admins = $this->userRepository->findByRole('ADMIN');
        $responsables = [];
        foreach ($sellerById as $sellerUser) {
            $label = trim((string) ($sellerUser->getDisplayName() ?: $sellerUser->getEmail()));
            if ($label !== '') {
                $responsables[] = $label;
            }
        }
        $responsablesText = $responsables !== [] ? implode(', ', array_unique($responsables)) : 'Non déterminé';

        foreach ($admins as $admin) {
            $notif = new Notifications();
            $notif->setUserId((int) $admin->getId());
            $notif->setType('order_created_admin');
            $notif->setTitle('📦 Nouvelle commande marketplace');
            $notif->setBody(sprintf(
                'Commande %s créée par %s. Responsable(s): %s. Montant: %s DT.',
                $orderRef,
                $buyerLabel,
                $responsablesText,
                $total
            ));
            $notif->setCommandeId($commande->getId());
            $notif->setCreatedAt(new \DateTimeImmutable());
            $this->notificationsRepository->save($notif, flush: false);
        }

        $adminIds = array_values(array_filter(array_map(static fn (User $u): int => (int) ($u->getId() ?? 0), $admins)));
        if ($adminIds !== []) {
            $adminPushResult = $this->oneSignalPushService->sendToUserIds(
                $adminIds,
                'Nouvelle commande marketplace',
                sprintf('Commande %s créée par %s.', $orderRef, $buyerLabel),
                [
                    'type' => 'order_created_admin',
                    'orderRef' => $orderRef,
                    'commandeId' => (string) ($commande->getId() ?? 0),
                    'targetRole' => 'admin',
                ]
            );
            if (($adminPushResult['ok'] ?? false) !== true) {
                $this->logger->warning('Admin OneSignal push failed', [
                    'commandeId' => $commande->getId(),
                    'orderRef' => $orderRef,
                    'result' => $adminPushResult,
                ]);
            }
        }

        $this->entityManager->flush();
    }

    private function formatOrderRef(Commandes $commande): string
    {
        $id = $commande->getId();
        if ($id > 0) {
            return '#CMD'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        }

        return (string) $commande->getNumCommande();
    }
}
