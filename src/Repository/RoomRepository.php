<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, 'App\Entity\Room');
    }

    //    /**
    //     * @return Room[] Returns an array of Room objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Room
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find minimum room price for active rooms
     */
    public function findMinPrice(): ?float
    {
        $qb = $this->createQueryBuilder('r');
        
        $result = $qb->select('MIN(r.pricePerNight) as minPrice')
            ->where('r.status = :status')
            ->setParameter('status', 'Available')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['minPrice'] ? (float) $result['minPrice'] : null;
    }

    /**
     * Find available rooms for landing page (limit to tease)
     */
    public function findForLandingPage(int $limit = 4): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', 'Available')
            ->orderBy('r.pricePerNight', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total active rooms
     */
    public function countActiveRooms(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', 'Available')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
