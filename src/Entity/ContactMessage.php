<?php

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post(),
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['contact:read']],
    denormalizationContext: ['groups' => ['contact:write']]
)]
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['contact:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['contact:read', 'contact:write'])]
    private ?string $message = null;

    #[ORM\Column(length: 20)]
    #[Groups(['contact:read'])]
    private string $status = 'unread';

    #[ORM\Column]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['contact:read'])]
    private ?string $notes = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['contact:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['contact:read'])]
    private ?\DateTimeImmutable $userSeenAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pushSentAt = null;

    #[ORM\OneToMany(mappedBy: 'contactMessage', targetEntity: ContactReply::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Groups(['contact:read'])]
    private Collection $replies;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function markAsRead(): void
    {
        if ($this->status === 'unread') {
            $this->status = 'read';
            $this->readAt = new \DateTimeImmutable();
        }
    }

    public function getUserSeenAt(): ?\DateTimeImmutable
    {
        return $this->userSeenAt;
    }

    public function markSeenByUser(): void
    {
        if ($this->userSeenAt === null) {
            $this->userSeenAt = new \DateTimeImmutable();
        }
    }

    public function getPushSentAt(): ?\DateTimeImmutable
    {
        return $this->pushSentAt;
    }

    public function markPushSent(): void
    {
        $this->pushSentAt = new \DateTimeImmutable();
    }

    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(ContactReply $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setContactMessage($this);
        }
        return $this;
    }

    public function removeReply(ContactReply $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getContactMessage() === $this) {
                $reply->setContactMessage(null);
            }
        }
        return $this;
    }
}