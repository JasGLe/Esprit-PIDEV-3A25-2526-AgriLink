<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\MarketplaceUserEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceUserEvent>
 *
 * @method MarketplaceUserEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceUserEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceUserEvent[]    findAll()
 * @method MarketplaceUserEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class MarketplaceUserEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceUserEvent::class);
    }

    public function save(MarketplaceUserEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<string, float> product_id => weighted_score
     */
    public function fetchUserProductInteractionScores(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('e')
            ->select('e.productId AS product_id', 'e.eventType AS type', 'COUNT(e.id) AS cnt')
            ->where('e.userId = :uid')
            ->andWhere('e.productId IS NOT NULL')
            ->setParameter('uid', $userId)
            ->groupBy('e.productId, e.eventType')
            ->getQuery()
            ->getArrayResult();

        $weights = [
            'view_product' => 1.0,
            'add_to_cart' => 3.0,
            'purchase' => 5.0,
        ];

        $scores = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            $cnt = (float) ($row['cnt'] ?? 0.0);
            $w = $weights[$type] ?? 0.5;
            $key = (string) $pid;
            $scores[$key] = ($scores[$key] ?? 0.0) + ($cnt * $w);
        }

        return $scores;
    }

    /**
     * @return list<string>
     */
    public function fetchRecentSearchQueries(int $userId, int $limit = 30): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('e')
            ->select('e.queryText AS q')
            ->where('e.userId = :uid')
            ->andWhere('e.eventType = :type')
            ->andWhere('e.queryText IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('type', 'search')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            if ($q !== '') {
                $out[] = $q;
            }
        }

        return $out;
    }

    public function findLastViewedProductId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $row = $this->createQueryBuilder('e')
            ->select('e.productId AS pid')
            ->where('e.userId = :uid')
            ->andWhere('e.eventType = :type')
            ->andWhere('e.productId IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('type', 'view_product')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!\is_array($row)) {
            return null;
        }
        $pid = (int) ($row['pid'] ?? 0);
        return $pid > 0 ? $pid : null;
    }
}

