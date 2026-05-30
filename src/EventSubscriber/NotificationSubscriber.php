<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\ContactMessage;
use App\Entity\ContactReply;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\UserRepository;
use App\Service\MercurePublisher;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class NotificationSubscriber
{
    public function __construct(
        private MercurePublisher  $mercure,
        private NotificationService $fcm,
        private UserRepository    $users,
    ) {}

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

        // New contact message from customer → push to all admin/staff
        if ($entity instanceof ContactMessage) {
            $senderName = $entity->getFullName() ?: 'Guest';
            $title = 'New message from ' . $senderName;
            $body  = $entity->getSubject() ?: 'No subject';
            $data  = ['type' => 'message', 'messageId' => (string) $entity->getId()];

            foreach ($this->users->findAdminAndStaffWithFcmToken() as $staff) {
                try {
                    $this->fcm->sendToDevice($staff->getFcmToken(), $title, $body, $data);
                } catch (\Throwable) {}
            }
        }

        // New reply → notify the other party
        if ($entity instanceof ContactReply) {
            $message    = $entity->getContactMessage();
            $repliedBy  = $entity->getRepliedBy();
            if (!$message) return;

            $replierRoles = $repliedBy?->getRoles() ?? [];
            $isStaff = in_array('ROLE_ADMIN', $replierRoles, true)
                    || in_array('ROLE_STAFF', $replierRoles, true);

            if ($isStaff) {
                // Staff replied → notify the customer
                $customer = $this->users->findOneBy(['email' => $message->getEmail()]);
                if ($customer?->getFcmToken()) {
                    try {
                        $this->fcm->sendToDevice(
                            $customer->getFcmToken(),
                            'Reply to your message',
                            'Re: ' . $message->getSubject(),
                            ['type' => 'message', 'messageId' => (string) $message->getId()],
                            'contact_reply_' . $message->getId(),
                        );
                    } catch (\Throwable) {}
                }
            } else {
                // Customer replied → notify admin/staff
                $title = ($repliedBy?->getFullName() ?: 'Customer') . ' replied';
                $body  = 'Re: ' . $message->getSubject();
                $data  = ['type' => 'message', 'messageId' => (string) $message->getId()];

                foreach ($this->users->findAdminAndStaffWithFcmToken() as $staff) {
                    if ($staff->getId() === $repliedBy?->getId()) continue;
                    try {
                        $this->fcm->sendToDevice($staff->getFcmToken(), $title, $body, $data);
                    } catch (\Throwable) {}
                }
            }
        }
    }
}
