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
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CancellationRequests::class);
    }

    public function findOnePendingByCommandeId(int $commandeId): ?CancellationRequests
    {
        return $this->findOneBy([
            'commandeId' => $commandeId,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * @param list<int> $commandeIds
     *
     * @return array<int, true>
     */
    public function findPendingCommandeIdMap(array $commandeIds): array
    {
        if ($commandeIds === []) {
            return [];
        }

        $entities = $this->createQueryBuilder('cr')
            ->where('cr.commandeId IN (:ids)')
            ->andWhere('cr.status = :st')
            ->setParameter('ids', $commandeIds)
            ->setParameter('st', self::STATUS_PENDING)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($entities as $cr) {
            $out[$cr->getCommandeId()] = true;
        }

        return $out;
    }

    /**
     * @param list<int> $commandeIds
     */
    public function countPendingForCommandeIds(array $commandeIds): int
    {
        if ($commandeIds === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('cr')
            ->select('COUNT(cr.id)')
            ->where('cr.commandeId IN (:ids)')
            ->andWhere('cr.status = :st')
            ->setParameter('ids', $commandeIds)
            ->setParameter('st', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
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