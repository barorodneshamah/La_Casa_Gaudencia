<?php

namespace App\Entity;

use App\Repository\SpaRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SpaRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['spa:read']]),
        new Get(normalizationContext: ['groups' => ['spa:read', 'spa:detail']])
    ]
)]
class Spa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['spa:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['spa:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['spa:read', 'spa:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['spa:read'])]
    private ?string $price = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['spa:read'])]
    private ?string $duration = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['spa:read'])]
    private ?int $capacity = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['spa:read'])]
    private ?string $category = null;

    #[ORM\Column(length: 50)]
    #[Groups(['spa:read'])]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['spa:read'])]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['spa:detail'])]
    private ?array $galleryImages = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $isOffer = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->galleryImages = [];
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = $this->status ?? 'Available';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }

    public function getDuration(): ?string { return $this->duration; }
    public function setDuration(?string $duration): static { $this->duration = $duration; return $this; }

    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(?int $capacity): static { $this->capacity = $capacity; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getMainImage(): ?string { return $this->mainImage; }
    public function setMainImage(?string $mainImage): static { $this->mainImage = $mainImage; return $this; }

    public function getGalleryImages(): ?array { return $this->galleryImages ?? []; }
    public function setGalleryImages(?array $galleryImages): static { $this->galleryImages = $galleryImages ?? []; return $this; }

    public function getAllImages(): array
    {
        $images = [];
        if ($this->mainImage) {
            $images[] = $this->mainImage;
        }
        foreach ($this->galleryImages ?? [] as $img) {
            $images[] = $img;
        }
        return $images;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function isOffer(): bool { return $this->isOffer; }
    public function setIsOffer(bool $isOffer): static { $this->isOffer = $isOffer; return $this; }

    public function isNew(): bool
    {
        return $this->createdAt !== null && $this->createdAt >= new \DateTimeImmutable('-7 days');
    }
}
