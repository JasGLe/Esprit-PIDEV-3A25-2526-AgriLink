<?php

namespace App\Repository;

use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    public function save(Equipement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Equipement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countByStatut(int $userId): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.statut, COUNT(e.id) as total')
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r['statut'] ?? 'Inconnu'] = (int) $r['total'];
        }
        return $data;
    }

    public function countByCategorie(int $userId): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.categorie, COUNT(e.id) as total')
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.categorie')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r['categorie'] ?? 'Inconnu'] = (int) $r['total'];
        }
        return $data;
    }

    public function countByType(int $userId): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.type, COUNT(e.id) as total')
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.type')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r['type'] ?? 'Inconnu'] = (int) $r['total'];
        }
        return $data;
    }
}