<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Panier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Panier>
 *
 * @method Panier|null find($id, $lockMode = null, $lockVersion = null)
 * @method Panier|null findOneBy(array $criteria, array $orderBy = null)
 * @method Panier[]    findAll()
 * @method Panier[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    public function findLigneByUtilisateurEtProduit(int $userId, int $produitId): ?Panier
    {
        return $this->findOneBy([
            'idPersonne' => $userId,
            'idProduit' => $produitId,
        ]);
    }

    /**
     * Nombre total d’unités (somme des quantités).
     */
    public function countTotalArticlesPourUtilisateur(int $userId): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.quantite), 0)')
            ->where('p.idPersonne = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Nombre de lignes panier (= produits différents) — utilisé pour le badge et le titre « Mon panier (n) ».
     */
    public function countLignesProduitsPourUtilisateur(int $userId): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.idPersonne = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * @return Panier[]
     */
    public function findByUtilisateur(int $userId): array
    {
        return $this->findBy(['idPersonne' => $userId], ['dateAjout' => 'DESC']);
    }

    public function save(Panier $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Panier $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}