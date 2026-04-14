<?php

namespace App\Repository\Annonce;

use App\Entity\Annonce\Annonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annonce>
 *
 * @method Annonce|null find($id, $lockMode = null, $lockVersion = null)
 * @method Annonce|null findOneBy(array $criteria, array $orderBy = null)
 * @method Annonce[]    findAll()
 * @method Annonce[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnnonceRepository extends ServiceEntityRepository
{
    private const SORTS = [
        'recent' => ['a.id', 'DESC'],
        'az' => ['a.titre', 'ASC'],
        'za' => ['a.titre', 'DESC'],
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annonce::class);
    }

    public function save(Annonce $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Annonce $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Annonce[]
     */
    public function findForIndex(?string $search, string $sort): array
    {
        $qb = $this->createQueryBuilder('a');

        $search = trim((string) $search);
        if ($search !== '') {
            $qb
                ->andWhere('LOWER(a.titre) LIKE :search OR LOWER(a.type) LIKE :search OR LOWER(a.status) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        [$field, $direction] = self::SORTS[$sort] ?? self::SORTS['recent'];

        return $qb
            ->orderBy($field, $direction)
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
