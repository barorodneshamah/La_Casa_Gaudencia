<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $roomNumber = null;

    #[ORM\Column(length: 60)]
    private ?string $roomType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerNight = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $capacity = null;

    #[ORM\Column(length: 4000, nullable: true)]
    private ?string $features = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $galleryImages = null;

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $itemConfig = [];

    public function getItemConfig(string $type, $id): array
    {
        $configs = $this->itemConfig ?? [];
        $key = (string) $id;

        if (!isset($configs[$type]) || !is_array($configs[$type])) {
            return [];
        }

        return $configs[$type][$key] ?? [];
    }

    public function setItemConfig(array $itemConfig): self
    {
        $this->itemConfig = $itemConfig;
        return $this;
    }

    /**
     * @var Collection<int, Package>
     */
    #[ORM\ManyToMany(targetEntity: Package::class, mappedBy: 'rooms')]
    private Collection $packages;

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

    public function getRoomNumber(): ?string
    {
        return $this->roomNumber;
    }

    public function setRoomNumber(string $roomNumber): static
    {
        $this->roomNumber = $roomNumber;
        return $this;
    }

    public function getRoomType(): ?string
    {
        return $this->roomType;
    }

    public function setRoomType(string $roomType): static
    {
        $this->roomType = $roomType;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->roomType;
    }

    public function getPricePerNight(): ?string
    {
        return $this->pricePerNight;
    }

    public function setPricePerNight(string $pricePerNight): static
    {
        $this->pricePerNight = $pricePerNight;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getFeatures(): ?string
    {
        return $this->features;
    }

    public function setFeatures(?string $features): static
    {
        $this->features = $features;
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
        return $this->galleryImages;
    }

    public function setGalleryImages(?array $galleryImages): static
    {
        $this->galleryImages = $galleryImages;
        return $this;
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
            $package->addRoom($this);
        }
        return $this;
    }

    public function removePackage(Package $package): static
    {
        if ($this->packages->removeElement($package)) {
            $package->removeRoom($this);
        }
        return $this;
    }

    /**
     * Check if room is part of any package
     */
    public function isInPackage(): bool
    {
        return !$this->packages->isEmpty();
    }

    /**
     * Get active packages containing this room
     */
    public function getActivePackages(): Collection
    {
        return $this->packages->filter(function (Package $package) {
            return $package->isValid();
        });
    }

    /**
     * Check if room is part of a specific package
     */
    public function isInSpecificPackage(Package $package): bool
    {
        return $this->packages->contains($package);
    }

    /**
     * Get the best package deal for this room
     */
    public function getBestPackageDeal(): ?Package
    {
        $activePackages = $this->getActivePackages();
        
        if ($activePackages->isEmpty()) {
            return null;
        }

        $bestPackage = null;
        $highestDiscount = 0;

        foreach ($activePackages as $package) {
            $discount = (float) $package->getDiscountPercentage();
            if ($discount > $highestDiscount) {
                $highestDiscount = $discount;
                $bestPackage = $package;
            }
        }

        return $bestPackage;
    }

    /**
     * Get nights configured for this room in a package
     */
    public function getNightsInPackage(Package $package): int
    {
        $config = $package->getItemConfig('room', $this->id);
        return $config['nights'] ?? 1;
    }

    /**
     * Calculate price for package stay
     */
    public function getPriceForPackage(Package $package): string
    {
        $nights = $this->getNightsInPackage($package);
        $total = (float) $this->pricePerNight * $nights;
        return number_format($total, 2, '.', '');
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
}