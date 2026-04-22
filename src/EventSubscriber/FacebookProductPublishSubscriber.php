<?php

namespace App\EventSubscriber;

use App\Entity\Marketplace\Produits;
use App\Service\FacebookPublisherService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
final class FacebookProductPublishSubscriber
{
    public function __construct(
        private readonly FacebookPublisherService $facebookPublisherService,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Produits) {
            return;
        }

        $this->facebookPublisherService->publishNewProduct($entity);
    }
}

