<?php

namespace App\Repository\Forum;

use App\Entity\Forum\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 *
 * @method Forum|null find($id, $lockMode = null, $lockVersion = null)
 * @method Forum|null findOneBy(array $criteria, array $orderBy = null)
 * @method Forum[]    findAll()
 * @method Forum[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumRepository extends ServiceEntityRepository
{
    private const SORTS = [
        'recent' => ['f.dateCreation', 'DESC'],
        'az' => ['f.titre', 'ASC'],
        'za' => ['f.titre', 'DESC'],
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function save(Forum $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Forum $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Forum[]
     */
    public function findForIndex(?string $search, string $sort): array
    {
        $qb = $this->createQueryBuilder('f');

        $search = trim((string) $search);
        if ($search !== '') {
            $qb
                ->andWhere('LOWER(f.titre) LIKE :search OR LOWER(f.categorie) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        [$field, $direction] = self::SORTS[$sort] ?? self::SORTS['recent'];

        return $qb
            ->orderBy($field, $direction)
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
