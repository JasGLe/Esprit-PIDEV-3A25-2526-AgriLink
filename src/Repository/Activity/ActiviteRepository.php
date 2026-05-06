<?php

namespace App\Repository\Activity;

use App\Entity\Activity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 *
 * @method Activite|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activite|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activite[]    findAll()
 * @method Activite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * @return Activite[]
     */
    public function findAllOrderedByDateDesc(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.dateDebut', 'DESC')
            ->addOrderBy('a.idActivite', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Activite[]
     */
    public function findBySearchTypeAndStatus(?string $search, ?string $type, ?string $status, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.dateDebut', 'DESC')
            ->addOrderBy('a.idActivite', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search) {
            $qb
                ->andWhere('LOWER(a.titre) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($type) {
            $qb
                ->andWhere('a.typeActivite = :type')
                ->setParameter('type', $type);
        }

        if ($status) {
            $qb
                ->andWhere('a.statut = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total records matching search criteria
     */
    public function countBySearchTypeAndStatus(?string $search, ?string $type, ?string $status): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.idActivite)');

        if ($search) {
            $qb
                ->andWhere('LOWER(a.titre) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($type) {
            $qb
                ->andWhere('a.typeActivite = :type')
                ->setParameter('type', $type);
        }

        if ($status) {
            $qb
                ->andWhere('a.statut = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return string[]
     */
    public function findAvailableTypes(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT a.typeActivite AS type')
            ->where('a.typeActivite IS NOT NULL')
            ->orderBy('a.typeActivite', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $row): ?string => $row['type'] ?? null, $rows)));
    }

    /**
     * @return string[]
     */
    public function findAvailableStatuses(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT a.statut AS status')
            ->where('a.statut IS NOT NULL')
            ->orderBy('a.statut', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $row): ?string => $row['status'] ?? null, $rows)));
    }

    /**
     * @return Activite[]
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.dateDebut >= :startDate')
            ->andWhere('a.dateDebut <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.dateDebut', 'ASC')
            ->addOrderBy('a.idActivite', 'ASC')
            ->getQuery()
            ->getResult();
    }
}