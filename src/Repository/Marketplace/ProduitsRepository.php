<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Join;
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

    /**
     * Catalogue public : annonces boutique agriculteur visibles (actives uniquement).
     *
     * @return Produits[]
     */
    public function findPublicMarketplaceActifs(): array
    {
        return $this->findPublicMarketplaceCatalog(null, 'all', null, 'recent');
    }

    /**
     * @param int[]|null $sellerIds restrict to these seller user ids (e.g. region filter)
     * @param 'price_asc'|'price_desc'|'recent' $sort
     *
     * @return Produits[]
     */
    public function findPublicMarketplaceCatalog(?string $search, string $cat, ?array $sellerIds, string $sort): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.active = :actif')
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->setParameter('actif', true);

        if ($search !== null && $search !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $term = '%'.$like.'%';
            $qb->leftJoin(User::class, 'v', Join::WITH, 'v.id = p.idFournisseur')
                ->andWhere($qb->expr()->orX(
                    'p.nom LIKE :q',
                    'p.description LIKE :q',
                    'p.categorie LIKE :q',
                    'v.nom LIKE :q',
                    'v.ville LIKE :q',
                    'v.email LIKE :q',
                    'v.fournisseurRaisonSocial LIKE :q'
                ))
                ->setParameter('q', $term);
        }

        $cat = strtolower($cat);
        match ($cat) {
            'legume' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'LEGUME'),
            'fruit' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'FRUIT'),
            'graines' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'GRAINS'),
            'equipement' => $qb->andWhere('p.equipementId IS NOT NULL'),
            default => null,
        };

        if ($sellerIds !== null) {
            if ($sellerIds === []) {
                return [];
            }
            $qb->andWhere('p.idFournisseur IN (:sids)')
                ->setParameter('sids', $sellerIds);
        }

        match ($sort) {
            'price_desc' => $qb->orderBy('p.prixUnitaire', 'DESC'),
            'recent' => $qb->orderBy('p.id', 'DESC'),
            default => $qb->orderBy('p.prixUnitaire', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * @return int[]
     */
    public function findDistinctSellerIdsPublicCatalog(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.idFournisseur AS sid')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.active = :actif')
            ->andWhere('p.idFournisseur IS NOT NULL')
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->setParameter('actif', true)
            ->getQuery()
            ->getScalarResult();

        $out = [];
        foreach ($rows as $row) {
            $id = isset($row['sid']) ? (int) $row['sid'] : (isset($row[0]) ? (int) $row[0] : null);
            if ($id !== null && $id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
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
