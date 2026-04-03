<?php

namespace App\Repository;

use App\Entity\NotificationRead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRead>
 *
 * @method NotificationRead|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationRead|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationRead[]    findAll()
 * @method NotificationRead[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRead::class);
    }

    public function save(NotificationRead $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationRead $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}