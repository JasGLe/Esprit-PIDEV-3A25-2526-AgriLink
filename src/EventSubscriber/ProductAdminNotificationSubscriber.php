<?php

namespace App\EventSubscriber;

use App\Entity\Marketplace\Produits;
use App\Entity\Notifications;
use App\Repository\UserManagement\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsDoctrineListener(event: Events::postPersist)]
final class ProductAdminNotificationSubscriber
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Produits) {
            return;
        }

        $sellerId = (int) ($entity->getIdFournisseur() ?? 0);
        if ($sellerId <= 0) {
            return;
        }

        $seller = $this->userRepository->find($sellerId);
        if ($seller === null) {
            return;
        }

        $admins = $this->userRepository->findBy(['role' => 'ADMIN']);
        if ($admins === []) {
            return;
        }

        $reviewUrl = $this->urlGenerator->generate('admin_moderation_boutique_index');
        $productId = $entity->getId();
        if ($productId <= 0) {
            return;
        }

        foreach ($admins as $admin) {
            $notification = (new Notifications())
                ->setUserId((int) $admin->getId())
                ->setType('marketplace_product_submitted')
                ->setTitle('Nouveau produit en attente')
                ->setBody(sprintf(
                    '%s a ajouté le produit "%s" le %s. Review: %s',
                    $seller->getDisplayName(),
                    $entity->getNom(),
                    (new \DateTimeImmutable())->format('Y-m-d H:i'),
                    $reviewUrl
                ))
                ->setProductId($productId)
                ->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }
}

