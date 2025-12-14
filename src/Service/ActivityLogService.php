<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ActivityLogService
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private RequestStack $requestStack;
    private NormalizerInterface $normalizer;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        RequestStack $requestStack,
        NormalizerInterface $normalizer
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->normalizer = $normalizer;
    }

    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityName = null,
        ?string $description = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?User $user = null
    ): ActivityLog {
        $log = new ActivityLog();

        $currentUser = $user ?? $this->security->getUser();

        if ($currentUser instanceof User) {
            $log->setUser($currentUser);
            $log->setUsername($currentUser->getEmail() ?? $currentUser->getUserIdentifier());
            $log->setUserRole($this->getUserRoleString($currentUser));
        } else {
            $log->setUsername('Anonymous');
            $log->setUserRole('GUEST');
        }

        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setEntityName($entityName);
        $log->setDescription($description ?? $this->generateDescription($action, $entityType, $entityName));
        $log->setOldData($oldData);
        $log->setNewData($newData);

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    public function logLogin(User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_LOGIN,
            ActivityLog::ENTITY_USER,
            $user->getId(),
            $user->getEmail(),
            'User logged in: ' . $user->getEmail(),
            null,
            null,
            $user
        );
    }

    public function logLogout(User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_LOGOUT,
            ActivityLog::ENTITY_USER,
            $user->getId(),
            $user->getEmail(),
            'User logged out: ' . $user->getEmail(),
            null,
            null,
            $user
        );
    }

    public function logCreate(object $entity, ?string $entityName = null): ActivityLog
    {
        $entityType = $this->getEntityType($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $name = $entityName ?? $this->getEntityDisplayName($entity);

        return $this->log(
            ActivityLog::ACTION_CREATE,
            $entityType,
            $entityId,
            $name,
            "Created {$entityType}: {$name}",
            null,
            $this->serializeEntity($entity)
        );
    }

    public function logUpdate(object $entity, array $oldData, ?string $entityName = null): ActivityLog
    {
        $entityType = $this->getEntityType($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $name = $entityName ?? $this->getEntityDisplayName($entity);

        return $this->log(
            ActivityLog::ACTION_UPDATE,
            $entityType,
            $entityId,
            $name,
            "Updated {$entityType}: {$name}",
            $oldData,
            $this->serializeEntity($entity)
        );
    }

    public function logDelete(object $entity, ?string $entityName = null): ActivityLog
    {
        $entityType = $this->getEntityType($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $name = $entityName ?? $this->getEntityDisplayName($entity);

        return $this->log(
            ActivityLog::ACTION_DELETE,
            $entityType,
            $entityId,
            $name,
            "Deleted {$entityType}: {$name}",
            $this->serializeEntity($entity),
            null
        );
    }

    private function getEntityType(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private function getEntityDisplayName(object $entity): string
    {
        if (method_exists($entity, 'getName')) return $entity->getName() ?? 'N/A';
        if (method_exists($entity, 'getTitle')) return $entity->getTitle() ?? 'N/A';
        if (method_exists($entity, 'getEmail')) return $entity->getEmail() ?? 'N/A';
        if (method_exists($entity, '__toString')) return (string) $entity;
        if (method_exists($entity, 'getId')) return 'ID: ' . $entity->getId();

        return 'Unknown';
    }

    private function getUserRoleString(User $user): string
    {
        $roles = $user->getRoles();

        return match (true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_MANAGER', $roles) => 'MANAGER',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default => 'USER',
        };
    }

    private function generateDescription(string $action, ?string $entityType, ?string $entityName): string
    {
        if ($entityType && $entityName) {
            return "{$action} {$entityType}: {$entityName}";
        }
        return $action;
    }

    private function serializeEntity(object $entity): array
    {
        return $this->normalizer->normalize(
            $entity,
            null,
            [
                'ignored_attributes' => ['password'],
                'circular_reference_handler' => fn ($object) =>
                    method_exists($object, 'getId') ? $object->getId() : null,
            ]
        );
    }
}
