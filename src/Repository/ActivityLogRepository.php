<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function findByFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->orderBy('al.createdAt', 'DESC');

        // Filter by action
        if (!empty($filters['action'])) {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $filters['action']);
        }

        // Filter by entity type
        if (!empty($filters['entityType'])) {
            $qb->andWhere('al.entityType = :entityType')
               ->setParameter('entityType', $filters['entityType']);
        }

        // Filter by user
        if (!empty($filters['user'])) {
            $qb->andWhere('al.user = :user')
               ->setParameter('user', $filters['user']);
        }

        // Filter by date range
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('al.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('al.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo']->modify('+1 day'));
        }

        // Search in description or username
        if (!empty($filters['search'])) {
            $qb->andWhere('al.description LIKE :search OR al.username LIKE :search OR al.entityName LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)');

        if (!empty($filters['action'])) {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['entityType'])) {
            $qb->andWhere('al.entityType = :entityType')
               ->setParameter('entityType', $filters['entityType']);
        }

        if (!empty($filters['user'])) {
            $qb->andWhere('al.user = :user')
               ->setParameter('user', $filters['user']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('al.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('al.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo']->modify('+1 day'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getRecentLogs(int $limit = 10): array
    {
        return $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLogsByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStatsByAction(): array
    {
        return $this->createQueryBuilder('al')
            ->select('al.action, COUNT(al.id) as count')
            ->groupBy('al.action')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByEntityType(): array
    {
        return $this->createQueryBuilder('al')
            ->select('al.entityType, COUNT(al.id) as count')
            ->where('al.entityType IS NOT NULL')
            ->groupBy('al.entityType')
            ->getQuery()
            ->getResult();
    }
}

    //    /**
    //     * @return ActivityLog[] Returns an array of ActivityLog objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ActivityLog
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
