<?php

namespace App\Repository;

use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 *
 * @method Equipement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Equipement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Equipement[]    findAll()
 * @method Equipement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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







public function countByStatutForUser(array $equipementIds): array
{
    if (empty($equipementIds)) return [];

    $results = $this->createQueryBuilder('m')
        ->select('m.statut, COUNT(m.id) as total')
        ->where('m.equipementId IN (:ids)')
        ->setParameter('ids', $equipementIds)
        ->groupBy('m.statut')
        ->getQuery()
        ->getResult();

    $data = [];
    foreach ($results as $r) {
        $data[$r['statut'] ?? 'Inconnu'] = (int) $r['total'];
    }
    return $data;
}

public function countByTypeForUser(array $equipementIds): array
{
    if (empty($equipementIds)) return [];

    $results = $this->createQueryBuilder('m')
        ->select('m.type, COUNT(m.id) as total')
        ->where('m.equipementId IN (:ids)')
        ->setParameter('ids', $equipementIds)
        ->groupBy('m.type')
        ->getQuery()
        ->getResult();

    $data = [];
    foreach ($results as $r) {
        $data[$r['type'] ?? 'Inconnu'] = (int) $r['total'];
    }
    return $data;
}

public function countByMoisForUser(array $equipementIds): array
{
    if (empty($equipementIds)) return [];

    // 12 derniers mois initialisés à 0
    $mois = [];
    for ($i = 11; $i >= 0; $i--) {
        $date = new \DateTime("-$i months");
        $mois[$date->format('Y-m')] = 0;
    }

    $results = $this->createQueryBuilder('m')
        ->select("DATE_FORMAT(m.datePlanifiee, '%Y-%m') as mois, COUNT(m.id) as total")
        ->where('m.equipementId IN (:ids)')
        ->andWhere('m.datePlanifiee >= :debut')
        ->setParameter('ids', $equipementIds)
        ->setParameter('debut', new \DateTime('-12 months'))
        ->groupBy('mois')
        ->orderBy('mois', 'ASC')
        ->getQuery()
        ->getResult();

    foreach ($results as $r) {
        if (isset($mois[$r['mois']])) {
            $mois[$r['mois']] = (int) $r['total'];
        }
    }

    return $mois;
}

public function countEnRetardForUser(array $equipementIds): int
{
    if (empty($equipementIds)) return 0;

    return (int) $this->createQueryBuilder('m')
        ->select('COUNT(m.id)')
        ->where('m.equipementId IN (:ids)')
        ->andWhere('m.statut NOT IN (:statuts)')
        ->andWhere('m.datePlanifiee < :today')
        ->setParameter('ids', $equipementIds)
        ->setParameter('statuts', ['Terminée', 'Annulée'])
        ->setParameter('today', new \DateTime('today'))
        ->getQuery()
        ->getSingleScalarResult();
}
}