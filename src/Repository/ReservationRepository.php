<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->leftJoin('r.room', 'room')->addSelect('room')
            ->leftJoin('r.tour', 'tour')->addSelect('tour')
            ->leftJoin('r.package', 'pkg')->addSelect('pkg')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->where('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByGuest(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.guest = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPaidPending(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->where('r.status = :status')
            ->andWhere('r.paymentStatus = :paid')
            ->setParameter('status', Reservation::STATUS_PENDING)
            ->setParameter('paid', Reservation::PAYMENT_PAID)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ====== ADD THESE MISSING METHODS ======

    /**
     * Get count of reservations grouped by service type
     */
    public function getCountByServiceType(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.serviceType, COUNT(r.id) as count')
            ->groupBy('r.serviceType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            if ($result['serviceType']) {
                $counts[$result['serviceType']] = (int) $result['count'];
            }
        }
        return $counts;
    }

    /**
     * Find reservations by service type
     */
    public function findByServiceType(string $serviceType): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')->addSelect('g')
            ->leftJoin('r.room', 'room')->addSelect('room')
            ->leftJoin('r.tour', 'tour')->addSelect('tour')
            ->leftJoin('r.package', 'pkg')->addSelect('pkg')
            ->where('r.serviceType = :serviceType')
            ->setParameter('serviceType', $serviceType)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count reservations by service type and status
     */
    public function countByServiceTypeAndStatus(string $serviceType, string $status): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.serviceType = :serviceType')
            ->andWhere('r.status = :status')
            ->setParameter('serviceType', $serviceType)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}