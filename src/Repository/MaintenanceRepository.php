<?php

namespace App\Repository;

use App\Dto\Equipment\LabelCountDto;
use App\Entity\Maintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * MaintenanceRepository
 * ─────────────────────────────────────────────────────────────────────────────
 * Repository Doctrine pour l'entité Maintenance.
 *
 * Toutes les méthodes métier filtrent par une liste d'IDs d'équipements (et non
 * directement par userId) car la relation Maintenance ↔ Utilisateur est indirecte :
 *   Maintenance::equipementId → Equipement::id → Equipement::userlog = userId
 *
 * Pattern utilisé dans les contrôleurs :
 *   $ids = array_map(fn($e) => $e->getId(), $equipRepo->findBy(['userlog' => $userId]));
 *   $mainRepo->countTotalForUser($ids);
 *
 * Les méthodes retournent 0 / [] immédiatement si la liste d'IDs est vide,
 * pour éviter des requêtes SQL avec IN () vide (invalide en MySQL).
 *
 * @extends ServiceEntityRepository<Maintenance>
 *
 * @method Maintenance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Maintenance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Maintenance[]    findAll()
 * @method Maintenance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    /**
     * Persiste une maintenance en base avec flush optionnel.
     *
     * @param Maintenance $entity  Entité à persister
     * @param bool        $flush   Si true, exécute immédiatement le flush Doctrine
     */
    public function save(Maintenance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Marque une maintenance pour suppression avec flush optionnel.
     *
     * @param Maintenance $entity  Entité à supprimer
     * @param bool        $flush   Si true, exécute immédiatement le flush Doctrine
     */
    public function remove(Maintenance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne toutes les maintenances pour une liste d'IDs d'équipements.
     *
     * Triées par date planifiée décroissante (les plus récentes en premier).
     * Utilisée notamment par le passeport numérique et le diagnostic IA.
     *
     * @param int[] $ids  IDs des équipements dont on veut les maintenances
     *
     * @return Maintenance[]  Tableau d'entités Maintenance, ou [] si $ids est vide
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
     * Retourne une Query paginable (non exécutée) avec filtres optionnels.
     *
     * Utilisée par KnpPaginator dans MaintenanceController::index() pour paginer
     * les résultats côté serveur tout en appliquant des filtres dynamiques.
     * Retourne une query qui ne ramène rien si la liste d'IDs est vide
     * (WHERE 1 = 0) — KnpPaginator aura 0 résultats, pas d'erreur SQL.
     *
     * @param int[]  $ids     IDs des équipements à filtrer
     * @param string $search  Texte libre (description, technicien, catégorie, type)
     * @param string $statut  Statut exact (Planifiée, En cours, Terminée, Annulée)
     * @param string $type    Type exact (Préventive, Corrective)
     *
     * @return \Doctrine\ORM\Query  Query paginable triée par datePlanifiee DESC
     */
    public function findFilteredQuery(
        array  $ids,
        string $search = '',
        string $statut = '',
        string $type   = ''
    ): \Doctrine\ORM\Query {
        if (empty($ids)) {
            // Retourner une query qui ne ramène rien (évite une erreur SQL avec IN ())
            return $this->createQueryBuilder('m')
                ->where('1 = 0')
                ->getQuery();
        }

        $qb = $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.datePlanifiee', 'DESC');

        // ── Filtre recherche texte : description, technicien, catégorie, type ─
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

        // ── Filtre statut exact ──────────────────────────────────────────────
        if ($statut !== '') {
            $qb->andWhere('m.statut = :statut')->setParameter('statut', $statut);
        }

        // ── Filtre type exact (Préventive / Corrective) ──────────────────────
        if ($type !== '') {
            $qb->andWhere('m.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery();
    }

    /**
     * Compte le nombre total de maintenances pour les équipements de l'utilisateur.
     *
     * Utilisé pour le KPI "Total maintenances" dans MaintenanceController::index().
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return int  Nombre total de maintenances, ou 0 si $ids est vide
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
     * Calcule la somme des coûts de toutes les maintenances de l'utilisateur.
     *
     * Utilisé pour le KPI "Coût total" dans MaintenanceController::index().
     * Retourne 0.0 si aucun coût n'est renseigné (SUM de NULL = NULL en SQL).
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return float  Somme des coûts en DT, ou 0.0 si $ids est vide ou tous les coûts sont null
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

        // SUM() retourne NULL en SQL si tous les coûts sont null → on normalise à 0.0
        return (float) ($result ?? 0.0);
    }

    // ════════════════════════════════════════════════════════
    // Méthodes statistiques (StatistiquesController)
    // ════════════════════════════════════════════════════════

    /**
     * Compte les maintenances par statut pour les équipements d'un utilisateur.
     *
     * Utilisé par StatistiquesController pour le graphique "Répartition par statut".
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return array<string, int>  Ex: ['Planifiée' => 3, 'Terminée' => 8, 'Annulée' => 1]
     */
    public function countByStatutForUser(array $ids): array
    {
        if (empty($ids)) return [];

        /** @var list<LabelCountDto> $results */
        $results = $this->createQueryBuilder('m')
            ->select(sprintf(
                'NEW %s(m.statut, COUNT(m.id))',
                LabelCountDto::class
            ))
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('m.statut')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r->label ?? 'Inconnu'] = $r->total;
        }
        return $data;
    }

    /**
     * Compte les maintenances par type pour les équipements d'un utilisateur.
     *
     * Utilisé par StatistiquesController pour le graphique "Préventive vs Corrective".
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return array<string, int>  Ex: ['Préventive' => 5, 'Corrective' => 3]
     */
    public function countByTypeForUser(array $ids): array
    {
        if (empty($ids)) return [];

        /** @var list<LabelCountDto> $results */
        $results = $this->createQueryBuilder('m')
            ->select(sprintf(
                'NEW %s(m.type, COUNT(m.id))',
                LabelCountDto::class
            ))
            ->where('m.equipementId IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('m.type')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r->label ?? 'Inconnu'] = $r->total;
        }
        return $data;
    }

    /**
     * Compte les maintenances par mois sur les 12 derniers mois.
     *
     * Utilisé par StatistiquesController pour le graphique d'évolution mensuelle.
     *
     * Stratégie :
     *  1. Initialise un tableau des 12 derniers mois avec count = 0 (garantit
     *     que tous les mois apparaissent dans le graphique même sans donnée).
     *  2. Récupère en base toutes les maintenances sur les 12 derniers mois.
     *  3. Compte en PHP en incrémentant le mois correspondant.
     *
     * Ce comptage PHP (plutôt que GROUP BY SQL) évite les problèmes de
     * compatibilité de format de date entre MySQL et PHP selon la locale.
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return array<string, int>  Ex: ['2025-04' => 2, '2025-05' => 0, ..., '2026-04' => 3]
     */
    public function countByMoisForUser(array $ids): array
    {
        if (empty($ids)) return [];

        // Initialiser les 12 derniers mois à 0 pour une courbe continue
        $mois = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = new \DateTime("-$i months");
            $mois[$date->format('Y-m')] = 0;
        }

        // Récupérer toutes les maintenances des 12 derniers mois en une seule requête
        $maintenances = $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->andWhere('m.datePlanifiee >= :debut')
            ->setParameter('ids', $ids)
            ->setParameter('debut', new \DateTime('-12 months'))
            ->getQuery()
            ->getResult();

        // Compter manuellement en PHP par mois
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

    /**
     * Compte les maintenances en retard pour les équipements d'un utilisateur.
     *
     * Une maintenance est "en retard" si :
     *  - Son statut N'EST PAS "Terminée" ni "Annulée" (encore active).
     *  - Sa datePlanifiee est strictement antérieure à aujourd'hui.
     *
     * Cohérent avec Maintenance::isEnRetard() qui applique la même logique en PHP.
     * Utilisé pour le KPI "En retard" dans MaintenanceController::index()
     * et pour PasseportController::calculerSante().
     *
     * @param int[] $ids  IDs des équipements de l'utilisateur
     *
     * @return int  Nombre de maintenances actives avec date dépassée, ou 0 si $ids est vide
     */
    public function countEnRetardForUser(array $ids): int
    {
        if (empty($ids)) return 0;

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.equipementId IN (:ids)')
            ->andWhere('m.statut NOT IN (:statuts)')  // exclure Terminée et Annulée
            ->andWhere('m.datePlanifiee < :today')     // date passée
            ->setParameter('ids', $ids)
            ->setParameter('statuts', ['Terminée', 'Annulée'])
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les prochaines maintenances planifiées pour les équipements d'un utilisateur.
     *
     * Critères :
     *  - datePlanifiee >= aujourd'hui (maintenances futures ou du jour)
     *  - statut IN ('Planifiée', 'En cours') — maintenances encore actives
     *  - triées par datePlanifiee ASC — les plus proches en premier
     *
     * Utilisée par le dashboard agriculteur pour l'encart "Prochaines maintenances".
     *
     * @param int[] $ids    IDs des équipements de l'utilisateur
     * @param int   $limit  Nombre maximum de résultats (défaut : 3)
     *
     * @return Maintenance[]  Les $limit prochaines maintenances, ou [] si $ids est vide
     */
    public function findProchainesForUser(array $ids, int $limit = 3): array
    {
        if (empty($ids)) return [];

        return $this->createQueryBuilder('m')
            ->where('m.equipementId IN (:ids)')
            ->andWhere('m.datePlanifiee >= :today')
            ->andWhere('m.statut IN (:statuts)')
            ->setParameter('ids', $ids)
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('statuts', ['Planifiée', 'En cours'])
            ->orderBy('m.datePlanifiee', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
