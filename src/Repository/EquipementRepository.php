<?php

namespace App\Repository;

use App\Dto\Equipment\LabelCountDto;
use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * EquipementRepository
 * ─────────────────────────────────────────────────────────────────────────────
 * Repository Doctrine pour l'entité Equipement.
 *
 * Fournit les méthodes de requête personnalisées nécessaires au tableau de bord
 * statistique (StatistiquesController) ainsi que les méthodes utilitaires
 * save() et remove() pour expliciter les opérations de persistance.
 *
 * Toutes les méthodes de statistiques filtrent par userId pour n'opérer que
 * sur les équipements appartenant à l'agriculteur connecté.
 *
 * @extends ServiceEntityRepository<Equipement>
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
     * Persiste un équipement en base avec flush optionnel.
     *
     * @param Equipement $entity  Entité à persister
     * @param bool       $flush   Si true, exécute immédiatement le flush Doctrine
     */
    public function save(Equipement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Marque un équipement pour suppression avec flush optionnel.
     *
     * @param Equipement $entity  Entité à supprimer
     * @param bool       $flush   Si true, exécute immédiatement le flush Doctrine
     */
    public function remove(Equipement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Compte les équipements d'un utilisateur, regroupés par statut.
     *
     * Garantit que les 4 statuts canoniques apparaissent toujours dans le résultat
     * (même avec un count 0), pour que Chart.js affiche toujours 4 secteurs.
     * Les statuts null, vides ou inconnus sont normalisés vers "Hors service".
     *
     * @param int $userId  ID de l'utilisateur connecté
     *
     * @return array<string, int>  Ex: ['Actif' => 3, 'En panne' => 1, 'En maintenance' => 0, 'Hors service' => 2]
     */
    public function countByStatut(int $userId): array
    {
        // Valeurs canoniques — tout ce qui n'est pas dans cette liste est normalisé vers "Hors service"
        $statutsConnus = ['Actif', 'En panne', 'En maintenance', 'Hors service'];

        // Initialiser à 0 pour garantir que tous les statuts apparaissent dans le graphique
        $data = array_fill_keys($statutsConnus, 0);

        /** @var list<LabelCountDto> $results */
        $results = $this->createQueryBuilder('e')
            ->select(sprintf(
                'NEW %s(e.statut, COUNT(e.id))',
                LabelCountDto::class
            ))
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        foreach ($results as $r) {
            $statut = $r->label;
            // Normaliser : null / vide / valeur inconnue → "Hors service"
            if ($statut === null || $statut === '' || !in_array($statut, $statutsConnus, true)) {
                $statut = 'Hors service';
            }
            $data[$statut] = ($data[$statut] ?? 0) + $r->total;
        }

        return $data;
    }

    /**
     * Compte les équipements d'un utilisateur, regroupés par catégorie.
     *
     * Les catégories null sont normalisées vers 'Inconnu' pour éviter
     * les clés null dans le tableau retourné.
     *
     * @param int $userId  ID de l'utilisateur connecté
     *
     * @return array<string, int>  Ex: ['Véhicule Motorisé' => 2, 'Outil Agricole' => 5]
     */
    public function countByCategorie(int $userId): array
    {
        /** @var list<LabelCountDto> $results */
        $results = $this->createQueryBuilder('e')
            ->select(sprintf(
                'NEW %s(e.categorie, COUNT(e.id))',
                LabelCountDto::class
            ))
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.categorie')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r->label ?? 'Inconnu'] = $r->total;
        }
        return $data;
    }

    /**
     * Compte les équipements d'un utilisateur, regroupés par type.
     *
     * Trié par nombre décroissant pour que les types les plus fréquents
     * apparaissent en premier dans le graphique.
     *
     * @param int $userId  ID de l'utilisateur connecté
     *
     * @return array<string, int>  Ex: ['Tracteur' => 3, 'Pulvérisateur' => 2]
     */
    public function countByType(int $userId): array
    {
        /** @var list<LabelCountDto> $results */
        $results = $this->createQueryBuilder('e')
            ->select(sprintf(
                'NEW %s(e.type, COUNT(e.id))',
                LabelCountDto::class
            ))
            ->where('e.userlog = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('e.type')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($results as $r) {
            $data[$r->label ?? 'Inconnu'] = $r->total;
        }
        return $data;
    }
}
