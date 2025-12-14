<?php

namespace App\Entity;

use App\Repository\PackageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Package
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $originalPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $packagePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discountPercentage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxRedemptions = null;

    #[ORM\Column]
    private int $currentRedemptions = 0;

    #[ORM\Column(length: 50)]
    private ?string $status = 'Active';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $packageType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $galleryImages = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $termsAndConditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $inclusions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $exclusions = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationNights = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxGuests = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\ManyToMany(targetEntity: Tour::class)]
    #[ORM\JoinTable(name: 'package_tours')]
    private Collection $tours;

    #[ORM\ManyToMany(targetEntity: Food::class)]
    #[ORM\JoinTable(name: 'package_foods')]
    private Collection $foods;

    #[ORM\ManyToMany(targetEntity: Room::class)]
    #[ORM\JoinTable(name: 'package_rooms')]
    private Collection $rooms;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $itemConfig = [];

    public function __construct()
    {
        $this->tours = new ArrayCollection();
        $this->foods = new ArrayCollection();
        $this->rooms = new ArrayCollection();
        $this->galleryImages = [];
        $this->currentRedemptions = 0;
        $this->isFeatured = false;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->calculateOriginalPrice();
        $this->calculateDiscount();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->calculateDiscount();
    }

    // ========== GETTERS & SETTERS ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
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

    public function getOriginalPrice(): ?string
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(?string $originalPrice): static
    {
        $this->originalPrice = $originalPrice;
        return $this;
    }

    public function getPackagePrice(): ?string
    {
        return $this->packagePrice;
    }

    public function setPackagePrice(?string $packagePrice): static
    {
        $this->packagePrice = $packagePrice;
        return $this;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): static
    {
        $this->discountPercentage = $discountPercentage;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function getMaxRedemptions(): ?int
    {
        return $this->maxRedemptions;
    }

    public function setMaxRedemptions(?int $maxRedemptions): static
    {
        $this->maxRedemptions = $maxRedemptions;
        return $this;
    }

    public function getCurrentRedemptions(): int
    {
        return $this->currentRedemptions;
    }

    public function setCurrentRedemptions(int $currentRedemptions): static
    {
        $this->currentRedemptions = $currentRedemptions;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(?string $packageType): static
    {
        $this->packageType = $packageType;
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

    public function getGalleryImages(): array
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
        $this->galleryImages = array_values(array_filter(
            $this->galleryImages ?? [],
            fn($img) => $img !== $imagePath
        ));
        return $this;
    }

    public function getTermsAndConditions(): ?string
    {
        return $this->termsAndConditions;
    }

    public function setTermsAndConditions(?string $termsAndConditions): static
    {
        $this->termsAndConditions = $termsAndConditions;
        return $this;
    }

    public function getInclusions(): ?string
    {
        return $this->inclusions;
    }

    public function setInclusions(?string $inclusions): static
    {
        $this->inclusions = $inclusions;
        return $this;
    }

    public function getExclusions(): ?string
    {
        return $this->exclusions;
    }

    public function setExclusions(?string $exclusions): static
    {
        $this->exclusions = $exclusions;
        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): static
    {
        $this->durationDays = $durationDays;
        return $this;
    }

    public function getDurationNights(): ?int
    {
        return $this->durationNights;
    }

    public function setDurationNights(?int $durationNights): static
    {
        $this->durationNights = $durationNights;
        return $this;
    }

    public function getMaxGuests(): ?int
    {
        return $this->maxGuests;
    }

    public function setMaxGuests(?int $maxGuests): static
    {
        $this->maxGuests = $maxGuests;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ========== COLLECTIONS ==========

    public function getTours(): Collection
    {
        return $this->tours;
    }

    public function addTour(Tour $tour): static
    {
        if (!$this->tours->contains($tour)) {
            $this->tours->add($tour);
        }
        return $this;
    }

    public function removeTour(Tour $tour): static
    {
        $this->tours->removeElement($tour);
        return $this;
    }

    public function getFoods(): Collection
    {
        return $this->foods;
    }

    public function addFood(Food $food): static
    {
        if (!$this->foods->contains($food)) {
            $this->foods->add($food);
        }
        return $this;
    }

    public function removeFood(Food $food): static
    {
        $this->foods->removeElement($food);
        return $this;
    }

    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function addRoom(Room $room): static
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms->add($room);
        }
        return $this;
    }

    public function removeRoom(Room $room): static
    {
        $this->rooms->removeElement($room);
        return $this;
    }

    // ========== HELPER METHODS ==========

    /**
     * Get all images (main + gallery) combined
     */
    public function getAllImages(): array
    {
        $images = [];
        
        if ($this->mainImage) {
            $images[] = $this->mainImage;
        }
        
        foreach ($this->getGalleryImages() as $img) {
            if ($img && !in_array($img, $images, true)) {
                $images[] = $img;
            }
        }
        
        return $images;
    }

    /**
     * Get total count of included items (rooms + tours + foods)
     */
    public function getTotalItemsCount(): int
    {
        return $this->rooms->count() + $this->tours->count() + $this->foods->count();
    }

    /**
     * Get remaining redemption slots
     */
    public function getRemainingSlots(): ?int
    {
        if ($this->maxRedemptions === null) {
            return null; // Unlimited
        }
        
        return max(0, $this->maxRedemptions - $this->currentRedemptions);
    }

    /**
     * Calculate original price from all included items
     */
    public function calculateOriginalPrice(): void
    {
        $total = 0.00;

        foreach ($this->tours as $tour) {
            $total += (float) $tour->getPrice();
        }

        foreach ($this->foods as $food) {
            $total += (float) $food->getPrice();
        }

        foreach ($this->rooms as $room) {
            $total += (float) $room->getPricePerNight();
        }

        $this->originalPrice = number_format($total, 2, '.', '');
    }

    /**
     * Calculate discount percentage
     */
    public function calculateDiscount(): void
    {
        $original = (float) ($this->originalPrice ?? 0);
        $package = (float) ($this->packagePrice ?? 0);

        if ($original > 0 && $package > 0 && $original > $package) {
            $discount = (($original - $package) / $original) * 100;
            $this->discountPercentage = number_format(max(0, $discount), 2, '.', '');
        } else {
            $this->discountPercentage = null;
        }
    }

    /**
     * Get savings amount
     */
    public function getSavings(): float
    {
        $original = (float) ($this->originalPrice ?? 0);
        $package = (float) ($this->packagePrice ?? 0);
        
        return max(0, $original - $package);
    }

    /**
     * Check if package is currently valid
     */
    public function isValid(): bool
    {
        if ($this->status !== 'Active') {
            return false;
        }

        $now = new \DateTime();

        if ($this->validFrom && $now < $this->validFrom) {
            return false;
        }

        if ($this->validUntil && $now > $this->validUntil) {
            return false;
        }

        if ($this->maxRedemptions !== null && $this->currentRedemptions >= $this->maxRedemptions) {
            return false;
        }

        return true;
    }

    /**
     * Get formatted duration string
     */
    public function getDurationFormatted(): string
    {
        $parts = [];
        if ($this->durationDays) {
            $parts[] = $this->durationDays . 'D';
        }
        if ($this->durationNights) {
            $parts[] = $this->durationNights . 'N';
        }
        return implode('/', $parts) ?: 'N/A';
    }

    /**
     * Check if package has any images
     */
    public function hasImages(): bool
    {
        return $this->mainImage !== null || count($this->getGalleryImages()) > 0;
    }

    /**
     * Get the first available image (for thumbnails/cards)
     */
    public function getFirstImage(): ?string
    {
        if ($this->mainImage) {
            return $this->mainImage;
        }
        
        $gallery = $this->getGalleryImages();
        return $gallery[0] ?? null;
    }

    public function getItemConfig(string $type, $id): array
    {
        $configs = $this->itemConfig ?? [];
        $key = (string) $id;

        if (!isset($configs[$type]) || !is_array($configs[$type])) {
            return [];
        }

        return $configs[$type][$key] ?? [];
    }

    public function setItemConfig(array $itemConfig): static
    {
        $this->itemConfig = $itemConfig;
        return $this;
    }
}