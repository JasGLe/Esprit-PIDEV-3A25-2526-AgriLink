<?php

namespace App\Repository\UserManagement;

use App\Entity\UserManagement\SecurityEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityEvent>
 *
 * @method SecurityEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method SecurityEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method SecurityEvent[]    findAll()
 * @method SecurityEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecurityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEvent::class);
    }

    public function save(SecurityEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SecurityEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get security statistics for the last N days
     */
    public function getStatistics(int $days = 30): array
    {
        $date = new \DateTime("-{$days} days");

        $total = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        $byType = $this->createQueryBuilder('e')
            ->select('e.eventType, COUNT(e.id) as count')
            ->where('e.createdAt >= :date')
            ->setParameter('date', $date)
            ->groupBy('e.eventType')
            ->getQuery()
            ->getResult();

        $typeStats = [];
        foreach ($byType as $row) {
            $typeStats[$row['eventType']] = (int) $row['count'];
        }

        $loginSuccess = $typeStats[SecurityEvent::EVENT_LOGIN_SUCCESS] ?? 0;
        $loginFailed = $typeStats[SecurityEvent::EVENT_LOGIN_FAILED] ?? 0;
        $accountLocked = $typeStats[SecurityEvent::EVENT_ACCOUNT_LOCKED] ?? 0;
        $twoFaSuccess = $typeStats[SecurityEvent::EVENT_2FA_SUCCESS] ?? 0;
        $twoFaFailed = $typeStats[SecurityEvent::EVENT_2FA_FAILED] ?? 0;
        $passwordChanged = $typeStats[SecurityEvent::EVENT_PASSWORD_CHANGED] ?? 0;

        return [
            'total' => (int) $total,
            'byType' => $typeStats,
            'loginSuccess' => $loginSuccess,
            'loginFailed' => $loginFailed,
            'accountLocked' => $accountLocked,
            'twoFaSuccess' => $twoFaSuccess,
            'twoFaFailed' => $twoFaFailed,
            'passwordChanged' => $passwordChanged,
            'suspiciousActivity' => $typeStats[SecurityEvent::EVENT_SUSPICIOUS_ACTIVITY] ?? 0,
            'days' => $days,
        ];
    }

    /**
     * Find recent security events
     */
    public function findRecent(int $limit = 15): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by user
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find login history events for a specific user
     */
    public function findLoginHistoryByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->where('u.id = :userId')
            ->andWhere('e.eventType IN (:types)')
            ->setParameter('userId', $userId)
            ->setParameter('types', [
                SecurityEvent::EVENT_LOGIN_SUCCESS,
                SecurityEvent::EVENT_LOGIN_FAILED,
                SecurityEvent::EVENT_ACCOUNT_LOCKED,
                SecurityEvent::EVENT_OAUTH_LOGIN,
            ])
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by type
     */
    public function findByType(string $eventType, int $days = 30, int $limit = 100): array
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('e')
            ->where('e.eventType = :type')
            ->andWhere('e.createdAt >= :date')
            ->setParameter('type', $eventType)
            ->setParameter('date', $date)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}