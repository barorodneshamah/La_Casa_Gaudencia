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
        parent::__construct($registry, Room::class);
    }

    /**
     * Find minimum room price
     */
    public function findMinPrice(): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('MIN(r.pricePerNight) as minPrice')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['minPrice'] ? (float) $result['minPrice'] : null;
    }

    /**
     * Find random rooms for landing page
     */
    public function findForLandingPage(int $limit = 3): array
    {
        // Get all rooms first
        $allRooms = $this->createQueryBuilder('r')
            ->getQuery()
            ->getResult();
        
        // Shuffle and return limited
        if (count($allRooms) > 0) {
            shuffle($allRooms);
            return array_slice($allRooms, 0, $limit);
        }
        
        return [];
    }

    /**
     * Count total rooms
     */
    public function countActiveRooms(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}