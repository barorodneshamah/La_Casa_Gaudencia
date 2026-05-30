<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        $this->excludeAnonymousUserUpdateNoise($qb);

        // ORDER is a virtual action: CREATE + entityType=Reservation (customer bookings)
        if (!empty($filters['action'])) {
            if ($filters['action'] === ActivityLog::ACTION_ORDER) {
                $qb->andWhere('al.action = :action AND al.entityType = :orderEntity')
                   ->setParameter('action', ActivityLog::ACTION_CREATE)
                   ->setParameter('orderEntity', ActivityLog::ENTITY_RESERVATION);
            } else {
                $qb->andWhere('al.action = :action')
                   ->setParameter('action', $filters['action']);
            }
        }

        // Filter by entity type (only when not already set by ORDER virtual filter)
        if (!empty($filters['entityType']) && empty($filters['action']) || (
            !empty($filters['entityType']) && ($filters['action'] ?? '') !== ActivityLog::ACTION_ORDER
        )) {
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

    public function countCustomerOrders(): int
    {
        return (int) $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->andWhere('al.action = :action AND al.entityType = :entity')
            ->setParameter('action', ActivityLog::ACTION_CREATE)
            ->setParameter('entity', ActivityLog::ENTITY_RESERVATION)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)');
        $this->excludeAnonymousUserUpdateNoise($qb);

        if (!empty($filters['action'])) {
            if ($filters['action'] === ActivityLog::ACTION_ORDER) {
                $qb->andWhere('al.action = :action AND al.entityType = :orderEntity')
                   ->setParameter('action', ActivityLog::ACTION_CREATE)
                   ->setParameter('orderEntity', ActivityLog::ENTITY_RESERVATION);
            } else {
                $qb->andWhere('al.action = :action')
                   ->setParameter('action', $filters['action']);
            }
        }

        if (!empty($filters['entityType']) && empty($filters['action']) || (
            !empty($filters['entityType']) && ($filters['action'] ?? '') !== ActivityLog::ACTION_ORDER
        )) {
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
        $qb = $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit);
        $this->excludeAnonymousUserUpdateNoise($qb);

        return $qb->getQuery()->getResult();
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
        $qb = $this->createQueryBuilder('al')
            ->select('al.action, COUNT(al.id) as count')
            ->groupBy('al.action');
        $this->excludeAnonymousUserUpdateNoise($qb);

        return $qb->getQuery()->getResult();
    }

    public function getStatsByEntityType(): array
    {
        $qb = $this->createQueryBuilder('al')
            ->select('al.entityType, COUNT(al.id) as count')
            ->where('al.entityType IS NOT NULL')
            ->groupBy('al.entityType');
        $this->excludeAnonymousUserUpdateNoise($qb);

        return $qb->getQuery()->getResult();
    }

    public function excludeAnonymousUserUpdateNoise(QueryBuilder $qb, string $alias = 'al'): void
    {
        $qb->andWhere(sprintf(
            'NOT (%1$s.username = :noiseUsername AND %1$s.userRole = :noiseRole AND %1$s.action = :noiseAction AND %1$s.entityType = :noiseEntity)',
            $alias
        ))
            ->setParameter('noiseUsername', 'Anonymous')
            ->setParameter('noiseRole', 'GUEST')
            ->setParameter('noiseAction', ActivityLog::ACTION_UPDATE)
            ->setParameter('noiseEntity', ActivityLog::ENTITY_USER);
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
