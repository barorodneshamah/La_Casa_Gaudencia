<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsEntityListener(event: Events::postPersist, entity: Payment::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Payment::class)]
class PaymentMercureSubscriber
{
    public function __construct(private HubInterface $hub) {}

    public function postPersist(Payment $payment): void
    {
        $this->publish($payment);
    }

    public function postUpdate(Payment $payment): void
    {
        $this->publish($payment);
    }

    private function publish(Payment $payment): void
    {
        try {
            $reservation = $payment->getReservation();
            $guest       = $reservation?->getGuest();
            $this->hub->publish(new Update(
                '/topic/payments',
                json_encode([
                    'id'                => $payment->getId(),
                    'transactionRef'    => $payment->getTransactionReference(),
                    'status'            => $payment->getStatus(),
                    'amount'            => $payment->getAmount(),
                    'paymentMethod'     => $payment->getPaymentMethod(),
                    'referenceNumber'   => $payment->getReferenceNumber(),
                    'guestNotes'        => $payment->getGuestNotes(),
                    'proofUrl'          => $payment->getProofOfPayment(),
                    'reservationId'     => $reservation?->getId(),
                    'reservationCode'   => $reservation?->getReservationCode(),
                    'serviceType'       => $reservation?->getServiceType(),
                    'guestEmail'        => $guest?->getEmail(),
                    'guestName'         => $guest?->getFullName() ?? $guest?->getUsername(),
                    'createdAt'         => $payment->getCreatedAt()?->format('M d, Y h:i A'),
                    'hoursAgo'          => $payment->getCreatedAt()
                        ? (int) round((time() - $payment->getCreatedAt()->getTimestamp()) / 3600)
                        : 0,
                ])
            ));
        } catch (\Throwable) {
        }
    }
}
