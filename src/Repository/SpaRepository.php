<?php

namespace App\Repository;

use App\Entity\Spa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Spa>
 */
class SpaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spa::class);
    }

    public function findAvailable(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', 'Available')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}