<?php

namespace App\Repository;

use App\Entity\ShareWallPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShareWallPost>
 */
class ShareWallPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareWallPost::class);
    }

    /**
     * @return ShareWallPost[]
     */
    public function findPublished(int $limit = 30, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.author', 'u')
            ->where('p.status = :status')
            ->andWhere('p.authorNameOverride IS NULL')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere('u.roles NOT LIKE :staffRole')
            ->setParameter('status', 'published')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('staffRole', '%ROLE_STAFF%')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.author', 'u')
            ->where('p.status = :status')
            ->andWhere('p.authorNameOverride IS NULL')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere('u.roles NOT LIKE :staffRole')
            ->setParameter('status', 'published')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('staffRole', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ShareWallPost[]
     */
    public function findFeatured(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.authorNameOverride IS NOT NULL')
            ->setParameter('status', 'published')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
