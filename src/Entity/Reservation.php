<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    public const SERVICE_ROOM = 'room';
    public const SERVICE_TOUR = 'tour';
    public const SERVICE_PACKAGE = 'package';
    public const SERVICE_FOOD = 'food';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const PAYMENT_UNPAID = 'UNPAID';
    public const PAYMENT_PARTIAL = 'PARTIAL';
    public const PAYMENT_PAID = 'PAID';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reservationCode = null;

    // ====== ADD THIS PROPERTY ======
    #[ORM\Column(length: 20)]
    private ?string $serviceType = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    private ?Room $room = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkInDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkOutDate = null;

    #[ORM\ManyToOne(targetEntity: Tour::class)]
    private ?Tour $tour = null;

    #[ORM\Column(nullable: true)]
    private ?int $tourParticipants = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tourDate = null;

    #[ORM\ManyToOne(targetEntity: Package::class)]
    private ?Package $package = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $foodItems = [];

    #[ORM\Column(nullable: true)]
    private ?int $numberOfGuests = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialRequests = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $totalAmount = '0.00';

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 20)]
    private ?string $paymentStatus = self::PAYMENT_UNPAID;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'reservation', cascade: ['persist'])]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->reservationCode = 'RES-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // ====== ADD GETTER & SETTER FOR serviceType ======
    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    // ... keep all your existing getters and setters ...
    
    public function getId(): ?int { return $this->id; }
    public function getReservationCode(): ?string { return $this->reservationCode; }
    
    public function getGuest(): ?User { return $this->guest; }
    public function setGuest(?User $guest): self { $this->guest = $guest; return $this; }

    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(?Room $room): self { $this->room = $room; return $this; }

    public function getCheckInDate(): ?\DateTimeInterface { return $this->checkInDate; }
    public function setCheckInDate(?\DateTimeInterface $date): self { $this->checkInDate = $date; return $this; }

    public function getCheckOutDate(): ?\DateTimeInterface { return $this->checkOutDate; }
    public function setCheckOutDate(?\DateTimeInterface $date): self { $this->checkOutDate = $date; return $this; }

    public function getTour(): ?Tour { return $this->tour; }
    public function setTour(?Tour $tour): self { $this->tour = $tour; return $this; }

    public function getTourParticipants(): ?int { return $this->tourParticipants; }
    public function setTourParticipants(?int $num): self { $this->tourParticipants = $num; return $this; }

    public function getTourDate(): ?\DateTimeInterface { return $this->tourDate; }
    public function setTourDate(?\DateTimeInterface $date): self { $this->tourDate = $date; return $this; }

    public function getPackage(): ?Package { return $this->package; }
    public function setPackage(?Package $package): self { $this->package = $package; return $this; }

    public function getFoodItems(): ?array { return $this->foodItems; }
    public function setFoodItems(?array $items): self { $this->foodItems = $items; return $this; }

    public function getNumberOfGuests(): ?int { return $this->numberOfGuests; }
    public function setNumberOfGuests(?int $num): self { $this->numberOfGuests = $num; return $this; }

    public function getSpecialRequests(): ?string { return $this->specialRequests; }
    public function setSpecialRequests(?string $req): self { $this->specialRequests = $req; return $this; }

    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $phone): self { $this->contactPhone = $phone; return $this; }

    public function getTotalAmount(): ?string { return $this->totalAmount; }
    public function setTotalAmount(string $amount): self { $this->totalAmount = $amount; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getPaymentStatus(): ?string { return $this->paymentStatus; }
    public function setPaymentStatus(string $status): self { $this->paymentStatus = $status; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $user): self { $this->approvedBy = $user; return $this; }

    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $date): self { $this->approvedAt = $date; return $this; }

    public function getAdminNotes(): ?string { return $this->adminNotes; }
    public function setAdminNotes(?string $notes): self { $this->adminNotes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setReservation($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getReservation() === $this) {
                $payment->setReservation(null);
            }
        }
        return $this;
    }

    public function updatePaymentStatus(): self
    {
        $totalApproved = 0.0;

        foreach ($this->payments as $payment) {
            if ($payment->getStatus() === Payment::STATUS_APPROVED) {
                $totalApproved += (float) $payment->getAmount();
            }
        }

        $totalRequired = (float) $this->totalAmount;

        if ($totalApproved <= 0) {
            $this->paymentStatus = self::PAYMENT_UNPAID;
        } elseif ($totalApproved >= $totalRequired) {
            $this->paymentStatus = self::PAYMENT_PAID;
        } else {
            $this->paymentStatus = self::PAYMENT_PARTIAL;
        }

        return $this;
    }

    public function getTotalPaid(): float
    {
        $total = 0.0;
        foreach ($this->payments as $payment) {
            if ($payment->getStatus() === Payment::STATUS_APPROVED) {
                $total += (float) $payment->getAmount();
            }
        }
        return $total;
    }

    public function getRemainingBalance(): float
    {
        return (float) $this->totalAmount - $this->getTotalPaid();
    }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isPaid(): bool { return $this->paymentStatus === self::PAYMENT_PAID; }

    public function getNights(): int
    {
        if ($this->checkInDate && $this->checkOutDate) {
            return $this->checkInDate->diff($this->checkOutDate)->days;
        }
        return 0;
    }

    public function getReservationType(): string
    {
        $types = [];
        if ($this->room) $types[] = 'Room';
        if ($this->tour) $types[] = 'Tour';
        if ($this->package) $types[] = 'Package';
        if (!empty($this->foodItems)) $types[] = 'Food';
        return implode(' + ', $types) ?: 'N/A';
    }

    public function getStatusBadge(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_COMPLETED => 'info',
            default => 'secondary',
        };
    }

    public function getPaymentBadge(): string
    {
        return match($this->paymentStatus) {
            self::PAYMENT_UNPAID => 'danger',
            self::PAYMENT_PARTIAL => 'warning',
            self::PAYMENT_PAID => 'success',
            default => 'secondary',
        };
    }
}