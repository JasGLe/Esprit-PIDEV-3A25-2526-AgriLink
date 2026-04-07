<?php

namespace App\Repository\UserManagement;

use App\Entity\UserManagement\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get general statistics about users
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');

        $total = $qb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $verified = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.emailVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        $byRole = $this->createQueryBuilder('u')
            ->select('u.role, COUNT(u.id) as count')
            ->groupBy('u.role')
            ->getQuery()
            ->getResult();

        $roleStats = [];
        foreach ($byRole as $row) {
            $roleStats[$row['role']] = (int) $row['count'];
        }

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'inactive' => (int) $total - (int) $active,
            'verified' => (int) $verified,
            'unverified' => (int) $total - (int) $verified,
            'byRole' => $roleStats,
            'agriculteurs' => $roleStats[User::ROLE_AGRICULTEUR] ?? 0,
            'agriplus' => $roleStats[User::ROLE_AGRIPLUS] ?? 0,
            'fournisseurs' => $roleStats[User::ROLE_FOURNISSEUR] ?? 0,
            'admins' => $roleStats[User::ROLE_ADMIN] ?? 0,
            'users' => $roleStats[User::ROLE_USER] ?? 0,
        ];
    }

    /**
     * Get statistics for a specific period
     */
    public function getStatisticsForPeriod(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $newUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :from')
            ->andWhere('u.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'newUsers' => (int) $newUsers,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Find recent users
     */
    public function findRecentUsers(int $days = 7, int $limit = 10): array
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find paginated users with filters
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $role = null,
        ?bool $isActive = null,
        ?string $search = null,
        string $orderBy = 'createdAt',
        string $orderDir = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('u');

        if ($role !== null && $role !== '') {
            $qb->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        if ($isActive !== null) {
            $qb->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.nom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $validOrderFields = ['id', 'nom', 'email', 'role', 'createdAt', 'isActive'];
        if (!in_array($orderBy, $validOrderFields)) {
            $orderBy = 'createdAt';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $countQb = clone $qb;
        $total = $countQb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = ($page - 1) * $limit;

        $data = $qb->orderBy('u.' . $orderBy, $orderDir)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Find locked accounts
     */
    public function findLockedAccounts(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lockedUntil > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('u.lockedUntil', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with multiple failed login attempts
     */
    public function findUsersWithFailedLogins(int $minAttempts = 3): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.failedLoginAttempts >= :min')
            ->setParameter('min', $minAttempts)
            ->orderBy('u.failedLoginAttempts', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unverified accounts older than X days
     */
    public function findUnverifiedAccounts(int $olderThanDays = 7): array
    {
        $date = new \DateTime("-{$olderThanDays} days");

        return $this->createQueryBuilder('u')
            ->where('u.emailVerified = :verified')
            ->andWhere('u.createdAt <= :date')
            ->setParameter('verified', false)
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get account security statistics
     */
    public function getAccountSecurityStats(): array
    {
        $total = $this->count([]);

        $active = $this->count(['isActive' => true]);
        $inactive = $total - $active;

        $verified = $this->count(['emailVerified' => true]);
        $unverified = $total - $verified;

        $twoFactorEnabled = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.twoFactorEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();

        $lockedNow = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.lockedUntil > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        $withFailedAttempts = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.failedLoginAttempts > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'inactive' => (int) $inactive,
            'verified' => (int) $verified,
            'unverified' => (int) $unverified,
            'twoFactorEnabled' => (int) $twoFactorEnabled,
            'lockedNow' => (int) $lockedNow,
            'withFailedAttempts' => (int) $withFailedAttempts,
        ];
    }

    /**
     * @return int[] User ids matching the provided city (case-insensitive).
     */
    public function findUserIdsByVille(string $ville): array
    {
        $needle = trim($ville);
        if ($needle === '') {
            return [];
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);

        $rows = $this->createQueryBuilder('u')
            ->select('u.id AS id')
            ->andWhere('LOWER(COALESCE(u.ville, \'\')) = :ville')
            ->setParameter('ville', $needle)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}