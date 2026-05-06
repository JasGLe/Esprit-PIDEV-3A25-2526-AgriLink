<?php

namespace App\Service\Marketplace\Recommendation;

use App\Entity\Marketplace\MarketplaceUserEvent;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\MarketplaceUserEventRepository;

final class MarketplaceEventLogger
{
    public const TYPE_SEARCH = 'search';
    public const TYPE_VIEW_PRODUCT = 'view_product';
    public const TYPE_ADD_TO_CART = 'add_to_cart';
    public const TYPE_PURCHASE = 'purchase';

    public function __construct(
        private readonly MarketplaceUserEventRepository $repo,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function log(?User $user, string $type, ?int $productId = null, ?string $queryText = null, array $meta = []): void
    {
        if (!$user instanceof User || $user->getId() === null) {
            // Only for connected users (as requested)
            return;
        }

        $evt = (new MarketplaceUserEvent())
            ->setUserId((int) $user->getId())
            ->setEventType($type)
            ->setProductId($productId)
            ->setQueryText($queryText)
            ->setMetaJson($meta !== [] ? $meta : null)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->repo->save($evt, flush: true);
    }
}

