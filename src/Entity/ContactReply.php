<?php

namespace App\Entity;

use App\Repository\ContactReplyRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post(),
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['reply:read']],
    denormalizationContext: ['groups' => ['reply:write']]
)]
#[ORM\Entity(repositoryClass: ContactReplyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ContactReply
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reply:read', 'contact:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ContactMessage::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['reply:read', 'reply:write'])]
    private ?ContactMessage $contactMessage = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reply:read', 'reply:write', 'contact:read'])]
    private ?User $repliedBy = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['reply:read', 'reply:write', 'contact:read'])]
    private ?string $replyMessage = null;

    #[ORM\Column]
    #[Groups(['reply:read', 'contact:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContactMessage(): ?ContactMessage
    {
        return $this->contactMessage;
    }

    public function setContactMessage(?ContactMessage $contactMessage): static
    {
        $this->contactMessage = $contactMessage;
        return $this;
    }

    public function getRepliedBy(): ?User
    {
        return $this->repliedBy;
    }

    public function setRepliedBy(?User $repliedBy): static
    {
        $this->repliedBy = $repliedBy;
        return $this;
    }

    public function getReplyMessage(): ?string
    {
        return $this->replyMessage;
    }

    public function setReplyMessage(string $replyMessage): static
    {
        $this->replyMessage = $replyMessage;
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
}
