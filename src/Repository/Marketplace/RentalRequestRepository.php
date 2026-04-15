<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\RentalRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RentalRequest>
 */
class RentalRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RentalRequest::class);
    }

    /**
     * @return list<RentalRequest>
     */
    public function findForSeller(int $sellerId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.vendeurId = :sid')
            ->setParameter('sid', $sellerId)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(RentalRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RentalRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
