<?php

namespace App\Repository\Activity;

use App\Entity\Activity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 *
 * @method Evenement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evenement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evenement[]    findAll()
 * @method Evenement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function findAllOrderedByDateDesc(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.dateEvenement', 'DESC')
            ->addOrderBy('e.idEvenement', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findBySearchAndType(?string $search, ?string $type, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateEvenement', 'DESC')
            ->addOrderBy('e.idEvenement', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search) {
            $qb
                ->andWhere('LOWER(e.titre) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($type) {
            $qb
                ->andWhere('e.typeEvenement = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total records matching search criteria
     */
    public function countBySearchAndType(?string $search, ?string $type): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.idEvenement)');

        if ($search) {
            $qb
                ->andWhere('LOWER(e.titre) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($type) {
            $qb
                ->andWhere('e.typeEvenement = :type')
                ->setParameter('type', $type);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return string[]
     */
    public function findAvailableTypes(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('DISTINCT e.typeEvenement AS type')
            ->where('e.typeEvenement IS NOT NULL')
            ->orderBy('e.typeEvenement', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $row): ?string => $row['type'] ?? null, $rows)));
    }

    /**
     * @return Evenement[]
     */
    public function findLatestByOrganisateurId(int $organisateurId, int $limit = 6): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.organisateur', 'o')
            ->andWhere('o.id = :organisateurId')
            ->setParameter('organisateurId', $organisateurId)
            ->orderBy('e.dateEvenement', 'DESC')
            ->addOrderBy('e.idEvenement', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByOrganisateurId(int $organisateurId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.idEvenement)')
            ->innerJoin('e.organisateur', 'o')
            ->andWhere('o.id = :organisateurId')
            ->setParameter('organisateurId', $organisateurId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find events between two dates for calendar
     * @return Evenement[]
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        return $this->createQueryBuilder('e')
            ->andWhere('e.dateEvenement >= :startDate')
            ->andWhere('e.dateEvenement <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('e.dateEvenement', 'ASC')
            ->addOrderBy('e.idEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
