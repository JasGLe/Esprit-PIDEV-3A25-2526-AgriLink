<?php

namespace App\Repository;

use App\Entity\MarketplaceBans;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceBans>
 *
 * @method MarketplaceBans|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceBans|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceBans[]    findAll()
 * @method MarketplaceBans[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MarketplaceBansRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceBans::class);
    }

    public function save(MarketplaceBans $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MarketplaceBans $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}