<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    public function countUnread(): int
    {
        return $this->count(['status' => 'unread']);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.fullName LIKE :q OR c.email LIKE :q OR c.subject LIKE :q OR c.message LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStats(): array
    {
        return [
            'total' => $this->count([]),
            'unread' => $this->count(['status' => 'unread']),
            'read' => $this->count(['status' => 'read']),
            'replied' => $this->count(['status' => 'replied']),
            'archived' => $this->count(['status' => 'archived']),
        ];
    }
}