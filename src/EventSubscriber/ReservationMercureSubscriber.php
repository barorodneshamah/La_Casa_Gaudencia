<?php

namespace App\EventSubscriber;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsEntityListener(event: Events::postPersist, entity: Reservation::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Reservation::class)]
class ReservationMercureSubscriber
{
    public function __construct(private HubInterface $hub) {}

    public function postPersist(Reservation $reservation): void
    {
        $this->publish($reservation);
    }

    public function postUpdate(Reservation $reservation): void
    {
        $this->publish($reservation);
    }

    private function publish(Reservation $reservation): void
    {
        try {
            $this->hub->publish(new Update(
                '/topic/reservations',
                json_encode([
                    'id'              => $reservation->getId(),
                    'reservationCode' => $reservation->getReservationCode(),
                    'status'          => $reservation->getStatus(),
                    'paymentStatus'   => $reservation->getPaymentStatus(),
                    'serviceType'     => $reservation->getServiceType(),
                    'totalAmount'     => $reservation->getTotalAmount(),
                ])
            ));
        } catch (\Throwable) {
        }
    }
}
