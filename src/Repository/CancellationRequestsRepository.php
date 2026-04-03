<?php

namespace App\Repository;

use App\Entity\CancellationRequests;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CancellationRequests>
 *
 * @method CancellationRequests|null find($id, $lockMode = null, $lockVersion = null)
 * @method CancellationRequests|null findOneBy(array $criteria, array $orderBy = null)
 * @method CancellationRequests[]    findAll()
 * @method CancellationRequests[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CancellationRequestsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CancellationRequests::class);
    }

    public function save(CancellationRequests $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CancellationRequests $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}