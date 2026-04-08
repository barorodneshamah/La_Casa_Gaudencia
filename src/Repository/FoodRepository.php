<?php

namespace App\Repository;

use App\Entity\Food;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Food>
 */
class FoodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Food::class);
    }

    /**
     * Find minimum food price
     */
    public function findMinPrice(): ?float
    {
        $result = $this->createQueryBuilder('f')
            ->select('MIN(f.price) as minPrice')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['minPrice'] ? (float) $result['minPrice'] : null;
    }

    /**
     * Find random food items for landing page
     */
    public function findForLandingPage(int $limit = 3): array
    {
        // Get all food items first
        $allFood = $this->createQueryBuilder('f')
            ->getQuery()
            ->getResult();
        
        // Shuffle and return limited
        if (count($allFood) > 0) {
            shuffle($allFood);
            return array_slice($allFood, 0, $limit);
        }
        
        return [];
    }

    /**
     * Count total food items
     */
    public function countActiveFoodItems(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}