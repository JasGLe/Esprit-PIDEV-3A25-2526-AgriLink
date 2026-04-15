<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\RentalRequestRepository::class)]
#[ORM\Table(name: 'rental_requests')]
class RentalRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $produitId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $vendeurId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $locataireId;

    // ── Step 1: User info ────────────────────────────────────────────────────

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le nom complet est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 200,
        minMessage: 'Le nom doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'\-\.]+$/u',
        message: 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
    )]
    private string $fullName;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse e-mail « {{ value }} » n\'est pas valide.')]
    #[Assert\Length(max: 150, maxMessage: 'L\'e-mail ne peut pas dépasser {{ limit }} caractères.')]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\+?[\d\s\-\(\)]{6,20}$/',
        message: 'Le numéro de téléphone n\'est pas valide (chiffres, espaces, +, -, parenthèses autorisés).',
    )]
    #[Assert\Length(max: 30, maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.')]
    private string $phone;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotNull(message: 'La date de naissance est obligatoire.')]
    #[Assert\LessThanOrEqual(
        value: '-18 years',
        message: 'Vous devez avoir au moins 18 ans pour effectuer une demande de location.',
    )]
    #[Assert\GreaterThan(
        value: '-120 years',
        message: 'La date de naissance semble incorrecte.',
    )]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'adresse doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $address;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotBlank(message: 'Le numéro d\'identité est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 80,
        minMessage: 'Le numéro d\'identité doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le numéro d\'identité ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9\-\/]+$/',
        message: 'Le numéro d\'identité ne peut contenir que des lettres, chiffres, tirets et slashes.',
    )]
    private string $identityNumber;

    // ── Step 2: Rental details ───────────────────────────────────────────────

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    #[Assert\GreaterThan(
        value: 'now',
        message: 'La date de début doit être dans le futur.',
    )]
    private \DateTimeInterface $rentalStartAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    private \DateTimeInterface $rentalEndAt;

    #[ORM\Column(type: Types::FLOAT)]
    private float $rentalDurationHours;

    #[ORM\Column(type: Types::STRING, length: 40)]
    #[Assert\NotBlank(message: 'Le mode de paiement est obligatoire.')]
    #[Assert\Choice(
        choices: ['cash', 'bank_transfer', 'card'],
        message: 'Le mode de paiement sélectionné n\'est pas valide.',
    )]
    private string $paymentMethod;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le lieu d\'utilisation est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le lieu d\'utilisation doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le lieu d\'utilisation ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $usageLocation;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'La responsabilité du transport est obligatoire.')]
    #[Assert\Choice(
        choices: ['renter', 'seller'],
        message: 'La valeur sélectionnée pour le transport n\'est pas valide.',
    )]
    private string $transportResponsibility;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private float $totalPrice = 0.0;

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }
    public function getProduitId(): int { return $this->produitId; }
    public function setProduitId(int $produitId): static { $this->produitId = $produitId; return $this; }
    public function getVendeurId(): int { return $this->vendeurId; }
    public function setVendeurId(int $vendeurId): static { $this->vendeurId = $vendeurId; return $this; }
    public function getLocataireId(): int { return $this->locataireId; }
    public function setLocataireId(int $locataireId): static { $this->locataireId = $locataireId; return $this; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $fullName): static { $this->fullName = $fullName; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }
    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static { $this->dateNaissance = $dateNaissance; return $this; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): static { $this->address = $address; return $this; }
    public function getIdentityNumber(): string { return $this->identityNumber; }
    public function setIdentityNumber(string $identityNumber): static { $this->identityNumber = $identityNumber; return $this; }
    public function getRentalStartAt(): \DateTimeInterface { return $this->rentalStartAt; }
    public function setRentalStartAt(\DateTimeInterface $rentalStartAt): static { $this->rentalStartAt = $rentalStartAt; return $this; }
    public function getRentalEndAt(): \DateTimeInterface { return $this->rentalEndAt; }
    public function setRentalEndAt(\DateTimeInterface $rentalEndAt): static { $this->rentalEndAt = $rentalEndAt; return $this; }
    public function getRentalDurationHours(): float { return $this->rentalDurationHours; }
    public function setRentalDurationHours(float $rentalDurationHours): static { $this->rentalDurationHours = $rentalDurationHours; return $this; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $paymentMethod): static { $this->paymentMethod = $paymentMethod; return $this; }
    public function getUsageLocation(): string { return $this->usageLocation; }
    public function setUsageLocation(string $usageLocation): static { $this->usageLocation = $usageLocation; return $this; }
    public function getTransportResponsibility(): string { return $this->transportResponsibility; }
    public function setTransportResponsibility(string $transportResponsibility): static { $this->transportResponsibility = $transportResponsibility; return $this; }
    public function getTotalPrice(): float { return $this->totalPrice; }
    public function setTotalPrice(float $totalPrice): static { $this->totalPrice = $totalPrice; return $this; }
}