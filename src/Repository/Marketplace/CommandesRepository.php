<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commandes;
use App\Entity\Marketplace\LigneCommande;
use App\Entity\UserManagement\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commandes>
 *
 * @method Commandes|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commandes|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commandes[]    findAll()
 * @method Commandes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commandes::class);
    }

    public function save(Commandes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Commandes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Commandes marketplace passées avec l’email du compte (même base que le desktop).
     *
     * @return list<Commandes>
     */
    public function findForMarketplaceClient(User $user): array
    {
        $email = strtolower(trim((string) $user->getEmail()));
        if ($email === '') {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(TRIM(c.email)) = :email')
            ->setParameter('email', $email)
            ->orderBy('c.dateCommande', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes marketplace contenant au moins une ligne vendue par cet utilisateur (`ligne_commande.id_fournisseur`).
     *
     * @return list<Commandes>
     */
    public function findForMarketplaceVendeur(int $sellerUserId): array
    {
        return $this->createQueryBuilder('c')
            ->distinct()
            ->innerJoin(LigneCommande::class, 'lc', Join::WITH, 'lc.idCommande = c.id')
            ->andWhere('lc.idFournisseur = :sid')
            ->setParameter('sid', $sellerUserId)
            ->orderBy('c.dateCommande', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les commandes marketplace (administration).
     *
     * @return list<Commandes>
     */
    public function findAllMarketplaceCommandes(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.dateCommande', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}