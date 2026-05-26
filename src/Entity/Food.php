<?php

namespace App\Entity;

use App\Repository\FoodRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: FoodRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['food:read', 'package:detail']]),
        new Get(normalizationContext: ['groups' => ['food:read', 'food:detail']])
    ]
)]
class Food
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['food:read', 'package:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['food:read', 'package:detail'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['food:read', 'food:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['food:read', 'package:detail'])]
    private ?string $price = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['food:read', 'package:detail'])]
    private ?string $category = null;

    #[ORM\Column]
    #[Groups(['food:read', 'food:detail'])]
    private ?int $availableStock = null;

    #[ORM\Column(length: 50)]
    #[Groups(['food:read', 'package:detail'])]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['food:read', 'package:detail'])]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['food:read', 'food:detail'])]
    private ?array $galleryImages = [];

    /**
     * @var Collection<int, Package>
     */
    #[ORM\ManyToMany(targetEntity: Package::class, mappedBy: 'foods')]
    private Collection $packages;

    #[ORM\Column(options: ['default' => false])]
    private bool $isOffer = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->galleryImages = [];
        $this->packages = new ArrayCollection();
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAvailableStock(): ?int
    {
        return $this->availableStock;
    }

    public function setAvailableStock(int $availableStock): static
    {
        $this->availableStock = $availableStock;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMainImage(): ?string
    {
        return $this->mainImage;
    }

    public function setMainImage(?string $mainImage): static
    {
        $this->mainImage = $mainImage;
        return $this;
    }

    public function getGalleryImages(): ?array
    {
        return $this->galleryImages ?? [];
    }

    public function setGalleryImages(?array $galleryImages): static
    {
        $this->galleryImages = $galleryImages ?? [];
        return $this;
    }

    public function addGalleryImage(string $imagePath): static
    {
        if (!in_array($imagePath, $this->galleryImages ?? [], true)) {
            $this->galleryImages[] = $imagePath;
        }
        return $this;
    }

    public function removeGalleryImage(string $imagePath): static
    {
        $this->galleryImages = array_filter($this->galleryImages ?? [], fn($img) => $img !== $imagePath);
        $this->galleryImages = array_values($this->galleryImages);
        return $this;
    }

    public function getAllImages(): array
    {
        $images = [];
        if ($this->mainImage) {
            $images[] = $this->mainImage;
        }
        return array_merge($images, $this->galleryImages ?? []);
    }

    // ========== PACKAGE RELATIONSHIP ==========

    /**
     * @return Collection<int, Package>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(Package $package): static
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
            $package->addFood($this);
        }
        return $this;
    }

    public function removePackage(Package $package): static
    {
        if ($this->packages->removeElement($package)) {
            $package->removeFood($this);
        }
        return $this;
    }

    /**
     * Check if food is part of any package
     */
    public function isInPackage(): bool
    {
        return !$this->packages->isEmpty();
    }

    /**
     * Get active packages containing this food
     */
    public function getActivePackages(): Collection
    {
        return $this->packages->filter(function (Package $package) {
            return $package->isValid();
        });
    }

    /**
     * Check if food is part of a specific package
     */
    public function isInSpecificPackage(Package $package): bool
    {
        return $this->packages->contains($package);
    }

    /**
     * Get quantity needed for packages (for stock management)
     */
    public function getPackageReservedQuantity(): int
    {
        $reserved = 0;
        
        foreach ($this->getActivePackages() as $package) {
            $config = $package->getItemConfig('food', $this->id);
            $quantity = $config['quantity'] ?? 1;
            $remainingSlots = $package->getRemainingSlots() ?? 0;
            $reserved += $quantity * $remainingSlots;
        }
        
        return $reserved;
    }

    // ========== TIMESTAMPS ==========

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isOffer(): bool { return $this->isOffer; }
    public function setIsOffer(bool $isOffer): static { $this->isOffer = $isOffer; return $this; }

    public function isNew(): bool
    {
        return $this->createdAt !== null && $this->createdAt >= new \DateTimeImmutable('-7 days');
    }
}