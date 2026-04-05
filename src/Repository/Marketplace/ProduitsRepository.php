<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Produits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produits>
 *
 * @method Produits|null find($id, $lockMode = null, $lockVersion = null)
 * @method Produits|null findOneBy(array $criteria, array $orderBy = null)
 * @method Produits[]    findAll()
 * @method Produits[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitsRepository extends ServiceEntityRepository
{
    /** @see BoutiqueController : origine boutique agriculteur, vendeur = id_fournisseur (id utilisateur) */
    public const ORIGINE_BOUTIQUE_AGRICULTEUR = 'BOUTIQUE_AGRICULTEUR';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produits::class);
    }

    /**
     * Annonces de la boutique personnelle (même schéma `produits`, sans colonne dédiée propriétaire).
     *
     * @return Produits[]
     */
    public function findBoutiqueByProprietaire(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.idFournisseur = :uid')
            ->andWhere('p.origine = :origine')
            ->setParameter('uid', $userId)
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Produits $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Produits $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
