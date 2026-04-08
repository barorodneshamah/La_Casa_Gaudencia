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

    /**
     * Find minimum tour price
     */
    public function findMinPrice(): ?float
    {
        $result = $this->createQueryBuilder('t')
            ->select('MIN(t.price) as minPrice')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['minPrice'] ? (float) $result['minPrice'] : null;
    }

    /**
     * Find random tours for landing page
     */
    public function findForLandingPage(int $limit = 3): array
    {
        // Get all tours first
        $allTours = $this->createQueryBuilder('t')
            ->getQuery()
            ->getResult();
        
        // Shuffle and return limited
        if (count($allTours) > 0) {
            shuffle($allTours);
            return array_slice($allTours, 0, $limit);
        }
        
        return [];
    }

    /**
     * Count total tours
     */
    public function countActiveTours(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}