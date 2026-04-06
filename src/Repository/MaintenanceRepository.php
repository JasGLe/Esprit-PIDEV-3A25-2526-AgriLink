<?php

namespace App\Repository;

use App\Entity\Maintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Maintenance>
 *
 * @method Maintenance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Maintenance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Maintenance[]    findAll()
 * @method Maintenance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    public function save(Maintenance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Maintenance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne toutes les maintenances pour une liste d'IDs d'équipements,
     * triées par date planifiée décroissante.
     */
    public function findByEquipementIds(array $ids): array
    {
        if (empty($ids)) return [];

        return $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.datePlanifiee', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ════════════════════════════════════════════════════════
    // Méthodes statistiques
    // ════════════════════════════════════════════════════════

    public function countByStatutForUser(array $ids): array
    {
        if (empty($ids)) return [];

        $results = $this->createQueryBuilder('m')
            ->select('m.statut, COUNT(m.id) as total')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('m.statut')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r['statut'] ?? 'Inconnu'] = (int) $r['total'];
        }
        return $data;
    }

    public function countByTypeForUser(array $ids): array
    {
        if (empty($ids)) return [];

        $results = $this->createQueryBuilder('m')
            ->select('m.type, COUNT(m.id) as total')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('m.type')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r['type'] ?? 'Inconnu'] = (int) $r['total'];
        }
        return $data;
    }

    public function countByMoisForUser(array $ids): array
    {
        if (empty($ids)) return [];

        // Initialiser les 12 derniers mois à 0
        $mois = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = new \DateTime("-$i months");
            $mois[$date->format('Y-m')] = 0;
        }

        // Récupérer toutes les maintenances des 12 derniers mois
        $maintenances = $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->andWhere('m.datePlanifiee >= :debut')
            ->setParameter('ids', $ids)
            ->setParameter('debut', new \DateTime('-12 months'))
            ->getQuery()
            ->getResult();

        // Compter manuellement en PHP
        foreach ($maintenances as $m) {
            if ($m->getDatePlanifiee()) {
                $key = $m->getDatePlanifiee()->format('Y-m');
                if (isset($mois[$key])) {
                    $mois[$key]++;
                }
            }
        }

        return $mois;
    }

    public function countEnRetardForUser(array $ids): int
    {
        if (empty($ids)) return 0;

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.equipementId IN (:ids)')
            ->andWhere('m.statut NOT IN (:statuts)')
            ->andWhere('m.datePlanifiee < :today')
            ->setParameter('ids', $ids)
            ->setParameter('statuts', ['Terminée', 'Annulée'])
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}