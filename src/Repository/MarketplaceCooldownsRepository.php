<?php

namespace App\Repository;

use App\Entity\MarketplaceCooldowns;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceCooldowns>
 *
 * @method MarketplaceCooldowns|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceCooldowns|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceCooldowns[]    findAll()
 * @method MarketplaceCooldowns[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MarketplaceCooldownsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceCooldowns::class);
    }

    public function save(MarketplaceCooldowns $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MarketplaceCooldowns $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}