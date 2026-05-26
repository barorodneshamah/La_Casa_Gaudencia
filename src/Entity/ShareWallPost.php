<?php

namespace App\Entity;

use App\Repository\ShareWallPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShareWallPostRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShareWallPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $caption = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUri = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $serviceTag = null;

    #[ORM\Column(length: 20)]
    private string $status = 'published';

    #[ORM\Column]
    private bool $isPinned = false;

    #[ORM\Column(type: Types::JSON)]
    private array $likedByIds = [];

    /**
     * @var Collection<int, ShareWallComment>
     */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: ShareWallComment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorNameOverride = null;

    #[ORM\Column(nullable: true)]
    private ?int $reservationId = null;

    #[ORM\Column(nullable: true)]
    private ?int $serviceId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): static
    {
        $this->caption = $caption;
        return $this;
    }

    public function getImageUri(): ?string
    {
        return $this->imageUri;
    }

    public function setImageUri(?string $imageUri): static
    {
        $this->imageUri = $imageUri;
        return $this;
    }

    public function getServiceTag(): ?string
    {
        return $this->serviceTag;
    }

    public function setServiceTag(?string $serviceTag): static
    {
        $this->serviceTag = $serviceTag;
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

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;
        return $this;
    }

    public function getLikedByIds(): array
    {
        return $this->likedByIds;
    }

    public function setLikedByIds(array $likedByIds): static
    {
        $this->likedByIds = $likedByIds;
        return $this;
    }

    public function toggleLike(int $userId): void
    {
        $ids = $this->likedByIds;
        $pos = array_search($userId, $ids, true);
        if ($pos === false) {
            $ids[] = $userId;
        } else {
            array_splice($ids, $pos, 1);
        }
        $this->likedByIds = array_values($ids);
    }

    /**
     * @return Collection<int, ShareWallComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(ShareWallComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }
        return $this;
    }

    public function removeComment(ShareWallComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }
        return $this;
    }

    public function getAuthorNameOverride(): ?string { return $this->authorNameOverride; }
    public function setAuthorNameOverride(?string $name): static { $this->authorNameOverride = $name; return $this; }

    public function getReservationId(): ?int { return $this->reservationId; }
    public function setReservationId(?int $reservationId): static { $this->reservationId = $reservationId; return $this; }

    public function getServiceId(): ?int { return $this->serviceId; }
    public function setServiceId(?int $serviceId): static { $this->serviceId = $serviceId; return $this; }

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
