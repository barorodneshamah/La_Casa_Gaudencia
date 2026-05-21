<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF') or is_granted('ROLE_GUEST')",
            normalizationContext: ['groups' => ['payment:read']]
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF') or (is_granted('ROLE_GUEST') and object.paidBy == user)",
            normalizationContext: ['groups' => ['payment:read', 'payment:detail']]
        ),
        new Post(
            security: "is_granted('ROLE_GUEST')",
            denormalizationContext: ['groups' => ['payment:write']]
        )
    ]
)]
class Payment
{
    // Payment Status Constants
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_REFUNDED = 'REFUNDED';
    public const STATUS_CANCELLED = 'CANCELLED';

    // Payment Method Constants
    public const METHOD_CASH = 'CASH';
    public const METHOD_CREDIT_CARD = 'CREDIT_CARD';
    public const METHOD_DEBIT_CARD = 'DEBIT_CARD';
    public const METHOD_GCASH = 'GCASH';
    public const METHOD_MAYA = 'MAYA';
    public const METHOD_PAYPAL = 'PAYPAL';
    public const METHOD_BANK_TRANSFER = 'BANK_TRANSFER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['payment:read'])]
    private ?string $transactionReference = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:write', 'payment:detail'])]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:write', 'payment:detail'])]
    private ?User $paidBy = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Groups(['payment:read', 'payment:write'])]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    private ?string $amount = null;

    #[ORM\Column(length: 30)]
    #[Groups(['payment:read', 'payment:write'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::METHOD_CASH, self::METHOD_CREDIT_CARD, self::METHOD_DEBIT_CARD,
        self::METHOD_GCASH, self::METHOD_MAYA, self::METHOD_PAYPAL, self::METHOD_BANK_TRANSFER,
    ])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 20)]
    #[Groups(['payment:read'])]
    #[Assert\Choice(choices: [
        self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED,
        self::STATUS_REFUNDED, self::STATUS_CANCELLED,
    ])]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:write', 'payment:detail'])]
    private ?string $proofOfPayment = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?string $referenceNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?string $guestNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment:detail'])]
    private ?string $adminNotes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment:detail'])]
    private ?User $approvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['payment:detail'])]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment:detail'])]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['payment:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->transactionReference = $this->generateTransactionReference();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[Assert\Callback]
    public function validateReferenceNumber(ExecutionContextInterface $context): void
    {
        if ($this->paymentMethod !== self::METHOD_CASH
            && (empty($this->referenceNumber) || $this->referenceNumber === 'N/A')
        ) {
            $context->buildViolation('A reference/transaction number is required for cashless payments.')
                ->atPath('referenceNumber')
                ->addViolation();
        }
    }

    private function generateTransactionReference(): string
    {
        return 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function setTransactionReference(string $transactionReference): self
    {
        $this->transactionReference = $transactionReference;
        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(?User $paidBy): self
    {
        $this->paidBy = $paidBy;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getProofOfPayment(): ?string
    {
        return $this->proofOfPayment;
    }

    public function setProofOfPayment(?string $proofOfPayment): self
    {
        $this->proofOfPayment = $proofOfPayment;
        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;
        return $this;
    }

    public function getGuestNotes(): ?string
    {
        return $this->guestNotes;
    }

    public function setGuestNotes(?string $guestNotes): self
    {
        $this->guestNotes = $guestNotes;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    // Helper Methods
    public function getPaymentMethodLabel(): string
    {
        return match($this->paymentMethod) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_DEBIT_CARD => 'Debit Card',
            self::METHOD_GCASH => 'GCash',
            self::METHOD_MAYA => 'Maya',
            self::METHOD_PAYPAL => 'PayPal',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            default => $this->paymentMethod,
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_REFUNDED => 'info',
            self::STATUS_CANCELLED => 'secondary',
            default => 'primary',
        };
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}