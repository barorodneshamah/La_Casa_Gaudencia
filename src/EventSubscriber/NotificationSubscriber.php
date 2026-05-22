<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Service\MercurePublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class NotificationSubscriber
{
    public function __construct(private MercurePublisher $mercure) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ActivityLog) {
            return;
        }

        if ($entity instanceof Reservation) {
            $this->mercure->publish('/topic/reservations', [
                'type'      => 'new_reservation',
                'id'        => $entity->getId(),
                'code'      => $entity->getReservationCode(),
                'guest'     => $entity->getGuest()?->getUsername(),
                'service'   => $entity->getServiceType(),
                'amount'    => $entity->getTotalAmount(),
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'message'   => 'New reservation from ' . ($entity->getGuest()?->getFullName() ?: $entity->getGuest()?->getUsername()),
            ]);
        }

        if ($entity instanceof Payment) {
            $this->mercure->publish('/topic/payments', [
                'type'      => 'new_payment',
                'id'        => $entity->getId(),
                'ref'       => $entity->getTransactionReference(),
                'guest'     => $entity->getPaidBy()?->getUsername(),
                'amount'    => $entity->getAmount(),
                'method'    => $entity->getPaymentMethod(),
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'message'   => 'Payment ₱' . $entity->getAmount() . ' submitted by ' . ($entity->getPaidBy()?->getFullName() ?: $entity->getPaidBy()?->getUsername()),
            ]);
        }
    }
}
