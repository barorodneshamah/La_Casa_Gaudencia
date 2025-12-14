<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Entity\Tour;
//use App\Entity\Package;
//use App\Entity\CustomItinerary;
use App\Entity\Food;
//use App\Entity\Booking;
use App\Entity\Room;
use App\Service\ActivityLogService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class EntityActivitySubscriber
{
    private ActivityLogService $activityLogService;
    private array $oldEntityData = [];
    
    // Entities to track
    private const TRACKED_ENTITIES = [
        User::class,
        Room::class,
        Tour::class,
        Food::class,
        #Package::class,
        #CustomItinerary::class,
        #Booking::class,
    ];

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Skip logging ActivityLog itself to prevent infinite loop
        if ($entity instanceof ActivityLog) {
            return;
        }

        if ($this->shouldTrackEntity($entity)) {
            $this->activityLogService->logCreate($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ActivityLog) {
            return;
        }

        if ($this->shouldTrackEntity($entity)) {
            // Store old data before update
            $this->oldEntityData[spl_object_id($entity)] = $args->getEntityChangeSet();
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ActivityLog) {
            return;
        }

        if ($this->shouldTrackEntity($entity)) {
            $objectId = spl_object_id($entity);
            $oldData = $this->oldEntityData[$objectId] ?? [];
            
            // Format old data for logging
            $formattedOldData = [];
            foreach ($oldData as $field => $values) {
                $formattedOldData[$field] = $values[0]; // [0] is old value, [1] is new value
            }

            $this->activityLogService->logUpdate($entity, $formattedOldData);
            
            // Clean up stored data
            unset($this->oldEntityData[$objectId]);
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ActivityLog) {
            return;
        }

        if ($this->shouldTrackEntity($entity)) {
            $this->activityLogService->logDelete($entity);
        }
    }

    private function shouldTrackEntity(object $entity): bool
    {
        foreach (self::TRACKED_ENTITIES as $trackedClass) {
            if ($entity instanceof $trackedClass) {
                return true;
            }
        }
        return false;
    }
}