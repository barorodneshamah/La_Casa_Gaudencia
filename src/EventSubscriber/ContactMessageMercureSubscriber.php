<?php

namespace App\EventSubscriber;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsEntityListener(event: Events::postPersist, entity: ContactMessage::class)]
class ContactMessageMercureSubscriber
{
    public function __construct(private HubInterface $hub) {}

    public function postPersist(ContactMessage $message): void
    {
        try {
            $this->hub->publish(new Update(
                '/topic/contact-messages',
                json_encode([
                    'event'    => 'new_inquiry',
                    'id'       => $message->getId(),
                    'fullName' => $message->getFullName(),
                    'subject'  => $message->getSubject(),
                ])
            ));
        } catch (\Throwable) {
        }
    }
}
