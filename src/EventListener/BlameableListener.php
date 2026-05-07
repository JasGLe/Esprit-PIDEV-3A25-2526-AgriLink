<?php

namespace App\EventListener;

use App\Entity\Trait\BlameableTrait;
use App\Entity\UserManagement\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: 'prePersist', priority: 500)]
#[AsDoctrineListener(event: 'preUpdate', priority: 500)]
final class BlameableListener
{
    private ?User $defaultUser = null;
    private bool $defaultUserResolved = false;

    public function __construct(private Security $security, private EntityManagerInterface $em)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->setBlameableFields($args->getObject(), true);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->setBlameableFields($args->getObject(), false);
    }

    private function setBlameableFields(object $entity, bool $isNew): void
    {
        // Check if the entity uses BlameableTrait
        if (!$this->usesBlameableTrait($entity)) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        
        // If no authenticated user, try to get a default user
        if (!$user instanceof User) {
            $user = $this->getDefaultUser();
        }

        if ($isNew) {
            if (!$user instanceof User) {
                throw new \LogicException(sprintf(
                    'Cannot persist %s without a creator. Ensure an authenticated user exists or user #1 is present.',
                    $entity::class
                ));
            }

            // Only set createdBy for new entities
            $this->setProperty($entity, 'createdBy', $user);
        }

        // Always set updatedBy if user is available
        if ($user instanceof User) {
            $this->setProperty($entity, 'updatedBy', $user);
        }
    }

    private function getDefaultUser(): ?User
    {
        if (!$this->defaultUserResolved) {
            $this->defaultUserResolved = true;
            // Try to find a default admin user (ID 1)
            $this->defaultUser = $this->em->getRepository(User::class)->find(1);
        }

        return $this->defaultUser;
    }

    private function usesBlameableTrait(object $entity): bool
    {
        $traitsUsed = class_uses($entity);
        return isset($traitsUsed[BlameableTrait::class]) || 
               in_array(BlameableTrait::class, $traitsUsed ?? [], true);
    }

    private function setProperty(object $entity, string $propertyName, mixed $value): void
    {
        $reflectionClass = new \ReflectionClass($entity);
        
        if ($reflectionClass->hasProperty($propertyName)) {
            $property = $reflectionClass->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($entity, $value);
        }
    }
}
