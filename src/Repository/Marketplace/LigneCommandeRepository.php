<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\LigneCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneCommande>
 *
 * @method LigneCommande|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneCommande|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneCommande[]    findAll()
 * @method LigneCommande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    public function save(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<LigneCommande>
     */
    public function findByCommandeAndFournisseur(int $commandeId, int $fournisseurUserId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.idCommande = :cid')
            ->andWhere('lc.idFournisseur = :fid')
            ->setParameter('cid', $commandeId)
            ->setParameter('fid', $fournisseurUserId)
            ->orderBy('lc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumMontantForFournisseurOnCommande(int $commandeId, int $fournisseurUserId): float
    {
        $v = $this->createQueryBuilder('lc')
            ->select('COALESCE(SUM(lc.prixTotal), 0)')
            ->andWhere('lc.idCommande = :cid')
            ->andWhere('lc.idFournisseur = :fid')
            ->setParameter('cid', $commandeId)
            ->setParameter('fid', $fournisseurUserId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $v;
    }
}