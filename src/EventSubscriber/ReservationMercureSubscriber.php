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
            $guest = $reservation->getGuest();
            $this->hub->publish(new Update(
                '/topic/reservations',
                json_encode([
                    'id'              => $reservation->getId(),
                    'reservationCode' => $reservation->getReservationCode(),
                    'status'          => $reservation->getStatus(),
                    'paymentStatus'   => $reservation->getPaymentStatus(),
                    'serviceType'     => $reservation->getServiceType(),
                    'totalAmount'     => $reservation->getTotalAmount(),
                    'guestEmail'      => $guest?->getEmail(),
                    'contactPhone'    => $reservation->getContactPhone(),
                    'createdAt'       => $reservation->getCreatedAt()?->format('M d, Y'),
                    'hasRoom'         => $reservation->getRoom() !== null,
                    'hasTour'         => $reservation->getTour() !== null,
                    'hasPackage'      => $reservation->getPackage() !== null,
                    'hasSpa'          => $reservation->getSpa() !== null,
                    'hasFood'         => $reservation->getFoodItems() !== null && !$reservation->getFoodItems()->isEmpty(),
                ])
            ));
        } catch (\Throwable) {
        }
    }
}
