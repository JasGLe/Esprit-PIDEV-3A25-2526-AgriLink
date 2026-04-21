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

    /**
     * Retourne une Query paginable avec filtres optionnels.
     * Utilisée par KnpPaginator dans MaintenanceController::index().
     */
    public function findFilteredQuery(
        array  $ids,
        string $search = '',
        string $statut = '',
        string $type   = ''
    ): \Doctrine\ORM\Query {
        if (empty($ids)) {
            // Retourner une query qui ne ramène rien
            return $this->createQueryBuilder('m')
                ->where('1 = 0')
                ->getQuery();
        }

        $qb = $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.datePlanifiee', 'DESC');

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('m.description', ':search'),
                    $qb->expr()->like('m.technicien', ':search'),
                    $qb->expr()->like('m.categorie', ':search'),
                    $qb->expr()->like('m.type', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        if ($statut !== '') {
            $qb->andWhere('m.statut = :statut')->setParameter('statut', $statut);
        }

        if ($type !== '') {
            $qb->andWhere('m.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery();
    }

    /**
     * Nombre total de maintenances pour les équipements de l'utilisateur.
     */
    public function countTotalForUser(array $ids): int
    {
        if (empty($ids)) return 0;

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Somme des coûts de toutes les maintenances de l'utilisateur.
     */
    public function sumCoutForUser(array $ids): float
    {
        if (empty($ids)) return 0.0;

        $result = $this->createQueryBuilder('m')
            ->select('SUM(m.cout)')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
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