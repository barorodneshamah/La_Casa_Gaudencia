<?php

namespace App\Entity;

use App\Repository\TourRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TourRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Tour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 160)]
    private ?string $duration = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $scheduleDate = null;

    #[ORM\Column]
    private ?int $availableSlots = null;

    #[ORM\Column(length: 150)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $galleryImages = null;

    /**
     * @var Collection<int, Package>
     */
    #[ORM\ManyToMany(targetEntity: Package::class, mappedBy: 'tours')]
    private Collection $packages;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'Available';
        $this->galleryImages = [];
        $this->packages = new ArrayCollection();
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
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

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getScheduleDate(): ?\DateTime
    {
        return $this->scheduleDate;
    }

    public function setScheduleDate(?\DateTime $scheduleDate): static
    {
        $this->scheduleDate = $scheduleDate;
        return $this;
    }

    public function getAvailableSlots(): ?int
    {
        return $this->availableSlots;
    }

    public function setAvailableSlots(int $availableSlots): static
    {
        $this->availableSlots = $availableSlots;
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
        $this->galleryImages = $galleryImages;
        return $this;
    }

    public function addGalleryImage(string $imageName): static
    {
        if (!in_array($imageName, $this->galleryImages ?? [])) {
            $this->galleryImages[] = $imageName;
        }
        return $this;
    }

    public function removeGalleryImage(string $imageName): static
    {
        if ($this->galleryImages) {
            $key = array_search($imageName, $this->galleryImages);
            if ($key !== false) {
                unset($this->galleryImages[$key]);
                $this->galleryImages = array_values($this->galleryImages);
            }
        }
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
            $package->addTour($this);
        }
        return $this;
    }

    public function removePackage(Package $package): static
    {
        if ($this->packages->removeElement($package)) {
            $package->removeTour($this);
        }
        return $this;
    }

    /**
     * Check if tour is part of any package
     */
    public function isInPackage(): bool
    {
        return !$this->packages->isEmpty();
    }

    /**
     * Get active packages containing this tour
     */
    public function getActivePackages(): Collection
    {
        return $this->packages->filter(function (Package $package) {
            return $package->isValid();
        });
    }

    /**
     * Check if tour is part of a specific package
     */
    public function isInSpecificPackage(Package $package): bool
    {
        return $this->packages->contains($package);
    }

    /**
     * Get the best package deal for this tour
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