<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findPendingPayments(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.reservation', 'r')
            ->leftJoin('p.paidBy', 'u')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByServiceType(string $serviceType, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.reservation', 'r')
            ->leftJoin('p.paidBy', 'u')
            ->where('r.serviceType = :serviceType')
            ->setParameter('serviceType', $serviceType)
            ->orderBy('p.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function getPendingCountByServiceType(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('r.serviceType, COUNT(p.id) as pending')
            ->leftJoin('p.reservation', 'r')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->groupBy('r.serviceType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['serviceType']] = $result['pending'];
        }
        return $counts;
    }

    public function getApprovedAmountByServiceType(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('r.serviceType, SUM(p.amount) as approved')
            ->leftJoin('p.reservation', 'r')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_APPROVED)
            ->groupBy('r.serviceType')
            ->getQuery()
            ->getResult();

        $amounts = [];
        foreach ($results as $result) {
            $amounts[$result['serviceType']] = (float) $result['approved'];
        }
        return $amounts;
    }

    public function getTotalPendingAmount(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTotalApprovedAmount(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTodayApprovedAmount(): float
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->andWhere('p.approvedAt >= :today')
            ->andWhere('p.approvedAt < :tomorrow')
            ->setParameter('status', Payment::STATUS_APPROVED)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function countPending(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}