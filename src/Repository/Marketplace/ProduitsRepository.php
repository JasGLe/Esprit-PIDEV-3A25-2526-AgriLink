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

        if ($search !== null && trim($search) !== '') {
            // Product-only search: name (keywords) and/or numeric product id — not region/city/seller.
            $normalized = preg_replace('/\s+/', ' ', trim($search)) ?? '';
            $keywords = preg_split('/\s+/', $normalized) ?: [];
            $keywords = array_values(array_filter($keywords, static fn ($k) => $k !== ''));

            foreach ($keywords as $i => $keyword) {
                $paramLike = 'q'.$i;
                $paramId = 'qid'.$i;

                // Whole token is a product id (e.g. "42" or "#019")
                if (preg_match('/^#?(\d+)$/', $keyword, $m)) {
                    $pid = (int) $m[1];
                    if ($pid > 0) {
                        $needle = function_exists('mb_strtolower') ? mb_strtolower($keyword) : strtolower($keyword);
                        $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);
                        $qb->andWhere($qb->expr()->orX(
                            'p.id = :'.$paramId,
                            'LOWER(COALESCE(p.nom, \'\')) LIKE :'.$paramLike
                        ))
                            ->setParameter($paramId, $pid)
                            ->setParameter($paramLike, '%'.$needle.'%');
                        continue;
                    }
                }

                $needle = function_exists('mb_strtolower') ? mb_strtolower($keyword) : strtolower($keyword);
                $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);

                if ($needle === '') {
                    continue;
                }

                // Single character: exact full name match is too strict; require prefix on name.
                if (strlen($needle) < 2) {
                    $qb->andWhere('LOWER(COALESCE(p.nom, \'\')) = :'.$paramLike)
                        ->setParameter($paramLike, $needle);

                    continue;
                }

                $qb->andWhere('LOWER(COALESCE(p.nom, \'\')) LIKE :'.$paramLike)
                    ->setParameter($paramLike, '%'.$needle.'%');
            }
        }

        $cat = strtolower($cat);
        match ($cat) {
            'legume' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'LEGUME'),
            'fruit' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'FRUIT'),
            'graines' => $qb->andWhere('p.category = :catcode')->setParameter('catcode', 'GRAINS'),
            'equipement' => $qb->andWhere('p.equipementId IS NOT NULL'),
            'location' => $qb->andWhere('p.equipementId IS NOT NULL')->andWhere('p.isRental = :isRental')->setParameter('isRental', true),
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

    /**
     * @return int[] culture ids already listed by this seller in boutique
     */
    public function findCultureIdsAlreadyInBoutiqueByOwner(int $userId): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.cultureId AS cid')
            ->andWhere('p.idFournisseur = :uid')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.cultureId IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->getQuery()
            ->getScalarResult();

        $out = [];
        foreach ($rows as $row) {
            $id = isset($row['cid']) ? (int) $row['cid'] : (isset($row[0]) ? (int) $row[0] : null);
            if ($id !== null && $id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    public function existsBoutiqueProduitForCulture(int $userId, int $cultureId): bool
    {
        $v = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.idFournisseur = :uid')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.cultureId = :cid')
            ->setParameter('uid', $userId)
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->setParameter('cid', $cultureId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $v > 0;
    }

    /**
     * @return int[] equipement ids already listed by this seller in boutique
     */
    public function findEquipementIdsAlreadyInBoutiqueByOwner(int $userId): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.equipementId AS eid')
            ->andWhere('p.idFournisseur = :uid')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.equipementId IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->getQuery()
            ->getScalarResult();

        $out = [];
        foreach ($rows as $row) {
            $id = isset($row['eid']) ? (int) $row['eid'] : (isset($row[0]) ? (int) $row[0] : null);
            if ($id !== null && $id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    public function existsBoutiqueProduitForEquipement(int $userId, int $equipementId): bool
    {
        $v = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.idFournisseur = :uid')
            ->andWhere('p.origine = :origine')
            ->andWhere('p.equipementId = :eid')
            ->setParameter('uid', $userId)
            ->setParameter('origine', self::ORIGINE_BOUTIQUE_AGRICULTEUR)
            ->setParameter('eid', $equipementId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $v > 0;
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
