<?php

namespace App\Repository\Marketplace;

use App\Dto\Marketplace\CategoryRevenueDto;
use App\Dto\Marketplace\SalesEvolutionPointDto;
use App\Dto\Marketplace\TopSellingProductDto;
use App\Entity\Marketplace\Commandes;
use App\Entity\Marketplace\LigneCommande;
use App\Entity\Marketplace\Produits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneCommande>
 *
 * @method LigneCommande|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneCommande|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneCommande[]    findAll()
 * @method LigneCommande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    public function save(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<LigneCommande>
     */
    public function findByCommandeAndFournisseur(int $commandeId, int $fournisseurUserId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.idCommande = :cid')
            ->andWhere('lc.idFournisseur = :fid')
            ->setParameter('cid', $commandeId)
            ->setParameter('fid', $fournisseurUserId)
            ->orderBy('lc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumMontantForFournisseurOnCommande(int $commandeId, int $fournisseurUserId): float
    {
        $v = $this->createQueryBuilder('lc')
            ->select('COALESCE(SUM(lc.prixTotal), 0)')
            ->andWhere('lc.idCommande = :cid')
            ->andWhere('lc.idFournisseur = :fid')
            ->setParameter('cid', $commandeId)
            ->setParameter('fid', $fournisseurUserId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $v;
    }

    /**
     * Pour l’admin : vendeurs distincts par commande (ids utilisateur sur les lignes).
     *
     * @param list<int> $commandeIds
     *
     * @return array<int, list<int>> id commande => ids fournisseur triés uniques
     */
    public function findDistinctFournisseurIdsGroupedByCommande(array $commandeIds): array
    {
        if ($commandeIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('lc')
            ->select('lc.idCommande AS cid', 'lc.idFournisseur AS fid')
            ->where('lc.idCommande IN (:ids)')
            ->andWhere('lc.idFournisseur IS NOT NULL')
            ->setParameter('ids', $commandeIds)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $cid = (int) $row['cid'];
            $fid = (int) $row['fid'];
            if (!isset($out[$cid])) {
                $out[$cid] = [];
            }
            $out[$cid][$fid] = true;
        }

        foreach ($commandeIds as $cid) {
            if (!isset($out[$cid])) {
                $out[$cid] = [];
            } else {
                $ids = array_keys($out[$cid]);
                sort($ids);
                $out[$cid] = $ids;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   revenue_today: float,
     *   revenue_month: float,
     *   revenue_year: float,
     *   orders_total: int
     * }
     */
    public function fetchSalesKpisForSeller(int $sellerUserId): array
    {
        $today = new \DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $yearStart = $today->setDate((int) $today->format('Y'), 1, 1);

        $row = $this->createQueryBuilder('lc')
            ->select(
                'COALESCE(SUM(CASE WHEN c.dateCommande = :today THEN lc.prixTotal ELSE 0 END), 0) AS revenue_today',
                'COALESCE(SUM(CASE WHEN c.dateCommande >= :monthStart THEN lc.prixTotal ELSE 0 END), 0) AS revenue_month',
                'COALESCE(SUM(CASE WHEN c.dateCommande >= :yearStart THEN lc.prixTotal ELSE 0 END), 0) AS revenue_year',
                'COUNT(DISTINCT c.id) AS orders_total'
            )
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->andWhere('lc.idFournisseur = :sid')
            ->andWhere('c.status != :cancelled')
            ->setParameter('sid', $sellerUserId)
            ->setParameter('cancelled', 'ANNULEE')
            ->setParameter('today', $today)
            ->setParameter('monthStart', $monthStart)
            ->setParameter('yearStart', $yearStart)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'revenue_today' => (float) ($row['revenue_today'] ?? 0),
            'revenue_month' => (float) ($row['revenue_month'] ?? 0),
            'revenue_year' => (float) ($row['revenue_year'] ?? 0),
            'orders_total' => (int) ($row['orders_total'] ?? 0),
        ];
    }

    /**
     * @return list<array{day_label: string, revenue: float}>
     */
    public function fetchSalesEvolutionLast30DaysForSeller(int $sellerUserId): array
    {
        $start = (new \DateTimeImmutable('today'))->modify('-29 days');
        /** @var list<SalesEvolutionPointDto> $rows */
        $rows = $this->createQueryBuilder('lc')
            ->select(sprintf(
                'NEW %s(c.dateCommande, COALESCE(SUM(lc.prixTotal), 0))',
                SalesEvolutionPointDto::class
            ))
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->andWhere('lc.idFournisseur = :sid')
            ->andWhere('c.status != :cancelled')
            ->andWhere('c.dateCommande >= :startDate')
            ->setParameter('sid', $sellerUserId)
            ->setParameter('cancelled', 'ANNULEE')
            ->setParameter('startDate', $start)
            ->groupBy('c.dateCommande')
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $byDay = [];
        foreach ($rows as $row) {
            if (!$row->dayDate instanceof \DateTimeInterface) {
                continue;
            }
            $key = $row->dayDate->format('Y-m-d');
            $byDay[$key] = $row->revenue;
        }

        $series = [];
        for ($i = 0; $i < 30; ++$i) {
            $day = $start->modify(sprintf('+%d days', $i));
            $key = $day->format('Y-m-d');
            $series[] = [
                'day_label' => $day->format('d/m'),
                'revenue' => $byDay[$key] ?? 0.0,
            ];
        }

        return $series;
    }

    /**
     * @return list<array{name: string, quantity: int, revenue: float}>
     */
    public function fetchTopSellingProductsForSeller(int $sellerUserId, int $limit = 5): array
    {
        /** @var list<TopSellingProductDto> $rows */
        $rows = $this->createQueryBuilder('lc')
            ->select(sprintf(
                'NEW %s(lc.nomProduit, SUM(lc.quantite), SUM(lc.prixTotal))',
                TopSellingProductDto::class
            ))
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->andWhere('lc.idFournisseur = :sid')
            ->andWhere('c.status != :cancelled')
            ->setParameter('sid', $sellerUserId)
            ->setParameter('cancelled', 'ANNULEE')
            ->groupBy('lc.nomProduit')
            ->orderBy('qty', 'DESC')
            ->addOrderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'name' => $row->productName,
                'quantity' => $row->quantity,
                'revenue' => $row->revenue,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{category: string, revenue: float}>
     */
    public function fetchSalesDistributionByCategoryForSeller(int $sellerUserId): array
    {
        /** @var list<CategoryRevenueDto> $rows */
        $rows = $this->createQueryBuilder('lc')
            ->select(sprintf(
                'NEW %s(p.categorie, SUM(lc.prixTotal))',
                CategoryRevenueDto::class
            ))
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->leftJoin(Produits::class, 'p', Join::WITH, 'p.id = lc.idProduit')
            ->andWhere('lc.idFournisseur = :sid')
            ->andWhere('c.status != :cancelled')
            ->setParameter('sid', $sellerUserId)
            ->setParameter('cancelled', 'ANNULEE')
            ->groupBy('p.categorie')
            ->orderBy('revenue', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $category = trim((string) ($row->category ?? ''));
            $out[] = [
                'category' => $category !== '' ? $category : 'Non classé',
                'revenue' => $row->revenue,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, float> product_id => sold_quantity
     */
    public function fetchGlobalTopSellingProductScores(): array
    {
        $rows = $this->createQueryBuilder('lc')
            ->select('lc.idProduit AS product_id', 'SUM(lc.quantite) AS qty')
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->andWhere('c.status != :cancelled')
            ->setParameter('cancelled', 'ANNULEE')
            ->groupBy('lc.idProduit')
            ->orderBy('qty', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $scores = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $scores[(string) $pid] = (float) ($row['qty'] ?? 0.0);
        }

        return $scores;
    }

    /**
     * @return array{
     *     productScores: array<string, float>,
     *     categoryScores: array<string, float>
     * }
     */
    public function fetchBuyerPurchaseSignalsByEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return ['productScores' => [], 'categoryScores' => []];
        }

        $rows = $this->createQueryBuilder('lc')
            ->select('lc.idProduit AS product_id', 'SUM(lc.quantite) AS qty', 'UPPER(COALESCE(p.category, \'\')) AS category_code')
            ->innerJoin(Commandes::class, 'c', Join::WITH, 'c.id = lc.idCommande')
            ->leftJoin(Produits::class, 'p', Join::WITH, 'p.id = lc.idProduit')
            ->andWhere('LOWER(COALESCE(c.email, \'\')) = :email')
            ->andWhere('c.status != :cancelled')
            ->setParameter('email', $email)
            ->setParameter('cancelled', 'ANNULEE')
            ->groupBy('lc.idProduit, p.category')
            ->getQuery()
            ->getArrayResult();

        $productScores = [];
        $categoryScores = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['qty'] ?? 0.0);
            $cat = strtoupper(trim((string) ($row['category_code'] ?? '')));
            if ($pid > 0 && $qty > 0) {
                $productScores[(string) $pid] = (($productScores[(string) $pid] ?? 0.0) + $qty);
            }
            if ($cat !== '' && $qty > 0) {
                $categoryScores[$cat] = (($categoryScores[$cat] ?? 0.0) + $qty);
            }
        }

        return [
            'productScores' => $productScores,
            'categoryScores' => $categoryScores,
        ];
    }
}
