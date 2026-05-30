<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['log:read']]
)]
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(columns: ['action'], name: 'idx_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class ActivityLog
{
    // Action Constants
    public const ACTION_LOGIN  = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_ORDER  = 'ORDER';

    // Entity Type Constants
    public const ENTITY_USER        = 'User';
    public const ENTITY_ROOM        = 'Room';
    public const ENTITY_TOUR        = 'Tour';
    public const ENTITY_FOOD        = 'Food';
    public const ENTITY_PACKAGE     = 'Package';
    public const ENTITY_SPA         = 'Spa';
    public const ENTITY_RESERVATION = 'Reservation';
    public const ENTITY_PAYMENT     = 'Payment';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['log:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['log:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['log:read'])]
    private ?string $username = null;

    #[ORM\Column(length: 50)]
    #[Groups(['log:read'])]
    private ?string $userRole = null;

    #[ORM\Column(length: 20)]
    #[Groups(['log:read'])]
    private ?string $action = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['log:read'])]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['log:read'])]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['log:read'])]
    private ?string $entityName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['log:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['log:read'])]
    private ?array $oldData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['log:read'])]
    private ?array $newData = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['log:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['log:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['log:read'])]
    private ?string $source = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['log:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getUserRole(): ?string
    {
        return $this->userRole;
    }

    public function setUserRole(?string $userRole): self
    {
        $this->userRole = $userRole;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): self
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getOldData(): ?array
    {
        return $this->oldData;
    }

    public function setOldData(?array $oldData): self
    {
        $this->oldData = $oldData;
        return $this;
    }

    public function getNewData(): ?array
    {
        return $this->newData;
    }

    public function setNewData(?array $newData): self
    {
        $this->newData = $newData;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSource(): ?string { return $this->source; }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getActionBadgeClass(): string
    {
        return match($this->action) {
            self::ACTION_LOGIN  => 'success',
            self::ACTION_LOGOUT => 'secondary',
            self::ACTION_CREATE => 'primary',
            self::ACTION_UPDATE => 'warning',
            self::ACTION_DELETE => 'danger',
            self::ACTION_ORDER  => 'order',
            default             => 'info',
        };
    }

    public function isCustomerOrder(): bool
    {
        return $this->entityType === self::ENTITY_RESERVATION
            && $this->action === self::ACTION_CREATE;
    }
}