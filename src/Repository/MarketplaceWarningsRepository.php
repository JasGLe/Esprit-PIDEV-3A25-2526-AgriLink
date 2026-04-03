<?php

namespace App\Repository;

use App\Entity\MarketplaceWarnings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceWarnings>
 *
 * @method MarketplaceWarnings|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceWarnings|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceWarnings[]    findAll()
 * @method MarketplaceWarnings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MarketplaceWarningsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceWarnings::class);
    }

    public function save(MarketplaceWarnings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MarketplaceWarnings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}