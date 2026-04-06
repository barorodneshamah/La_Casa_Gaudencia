<?php

namespace App\Repository;

use App\Entity\Tour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tour>
 */
class TourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tour::class);
    }

    //    /**
    //     * @return Tour[] Returns an array of Tour objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tour
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find minimum tour price for active tours
     */
    public function findMinPrice(): ?float
    {
        $qb = $this->createQueryBuilder('t');
        
        $result = $qb->select('MIN(t.price) as minPrice')
            ->where('t.status = :status')
            ->setParameter('status', 'Available')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['minPrice'] ? (float) $result['minPrice'] : null;
    }

    /**
     * Find available tours for landing page
     */
    public function findForLandingPage(int $limit = 4): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', 'Available')
            ->orderBy('t.price', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total active tours
     */
    public function countActiveTours(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', 'Available')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
