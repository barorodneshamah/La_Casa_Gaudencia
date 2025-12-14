<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    public function findActivePackages(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.validFrom IS NULL OR p.validFrom <= :now')
            ->andWhere('p.validUntil IS NULL OR p.validUntil >= :now')
            ->andWhere('p.maxRedemptions IS NULL OR p.currentRedemptions < p.maxRedemptions')
            ->setParameter('status', 'Active')
            ->setParameter('now', $now)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFeaturedPackages(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.isFeatured = true')
            ->setParameter('status', 'Active')
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.packageType = :type')
            ->setParameter('status', 'Active')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function findExpiringSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $future = (new \DateTime())->modify("+{$days} days");

        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.validUntil BETWEEN :now AND :future')
            ->setParameter('status', 'Active')
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('p.validUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }
}