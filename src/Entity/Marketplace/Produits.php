<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\ProduitsRepository::class)]
#[ORM\Table(name: 'produits')]
#[ORM\HasLifecycleCallbacks]
class Produits
{
    public const MODERATION_PENDING = 'pending';
    public const MODERATION_APPROVED = 'approved';
    public const MODERATION_BANNED = 'banned';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $nom;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $categorie;

    #[ORM\Column(name: 'prixUnitaire', type: Types::DECIMAL, precision: 12, scale: 3)]
    private string $prixUnitaire = '0.000';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $origine;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $idFournisseur = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $equipementId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $cultureId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantite = 0;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    private ?string $promoCode = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $promoDiscountPercent = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $promoActive = false;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $promoStartAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $promoEndAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isRental = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $rentalPricePerDay = null;

    #[ORM\Column(type: Types::STRING, length: 800, nullable: true)]
    private ?string $rentalDescription = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::MODERATION_PENDING])]
    private string $moderationStatus = self::MODERATION_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getPrixUnitaire(): float
    {
        return (float) $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = number_format($prixUnitaire, 3, '.', '');

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        if ($image === null || $image === '') {
            $this->image = $image;

            return $this;
        }

        // DB column is VARCHAR(50): keep filename safe and preserve extension when possible.
        $maxLen = 50;
        $len = function_exists('mb_strlen') ? mb_strlen($image) : strlen($image);
        if ($len > $maxLen) {
            $dotPos = strrpos($image, '.');
            if ($dotPos !== false && $dotPos > 0 && $dotPos < strlen($image) - 1) {
                $ext = substr($image, $dotPos + 1);
                $extLen = function_exists('mb_strlen') ? mb_strlen($ext) : strlen($ext);
                $baseMax = $maxLen - $extLen - 1;
                if ($baseMax > 0) {
                    $base = substr($image, 0, $dotPos);
                    $base = function_exists('mb_substr') ? mb_substr($base, 0, $baseMax) : substr($base, 0, $baseMax);
                    $image = $base.'.'.$ext;
                } else {
                    $image = function_exists('mb_substr') ? mb_substr($image, 0, $maxLen) : substr($image, 0, $maxLen);
                }
            } else {
                $image = function_exists('mb_substr') ? mb_substr($image, 0, $maxLen) : substr($image, 0, $maxLen);
            }
        }

        $this->image = $image;

        return $this;
    }

    public function getOrigine(): string
    {
        return $this->origine;
    }

    public function setOrigine(string $origine): static
    {
        $this->origine = $origine;

        return $this;
    }

    public function getIdFournisseur(): ?int
    {
        return $this->idFournisseur;
    }

    public function setIdFournisseur(?int $idFournisseur): static
    {
        $this->idFournisseur = $idFournisseur;

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getEquipementId(): ?int
    {
        return $this->equipementId;
    }

    public function setEquipementId(?int $equipementId): static
    {
        $this->equipementId = $equipementId;

        return $this;
    }

    public function getCultureId(): ?int
    {
        return $this->cultureId;
    }

    public function setCultureId(?int $cultureId): static
    {
        $this->cultureId = $cultureId;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): static
    {
        $this->promoCode = $promoCode !== null ? mb_strtoupper(trim($promoCode)) : null;

        return $this;
    }

    public function getPromoDiscountPercent(): ?float
    {
        return $this->promoDiscountPercent;
    }

    public function setPromoDiscountPercent(?float $promoDiscountPercent): static
    {
        $this->promoDiscountPercent = $promoDiscountPercent;

        return $this;
    }

    public function isPromoActive(): bool
    {
        return $this->promoActive;
    }

    public function setPromoActive(bool $promoActive): static
    {
        $this->promoActive = $promoActive;

        return $this;
    }

    public function getPromoStartAt(): ?\DateTimeInterface
    {
        return $this->promoStartAt;
    }

    public function setPromoStartAt(?\DateTimeInterface $promoStartAt): static
    {
        $this->promoStartAt = $promoStartAt;

        return $this;
    }

    public function getPromoEndAt(): ?\DateTimeInterface
    {
        return $this->promoEndAt;
    }

    public function setPromoEndAt(?\DateTimeInterface $promoEndAt): static
    {
        $this->promoEndAt = $promoEndAt;

        return $this;
    }

    public function isRental(): bool
    {
        return $this->isRental;
    }

    public function setIsRental(bool $isRental): static
    {
        $this->isRental = $isRental;

        return $this;
    }

    public function getRentalPricePerDay(): ?float
    {
        return $this->rentalPricePerDay !== null ? (float) $this->rentalPricePerDay : null;
    }

    public function setRentalPricePerDay(?float $rentalPricePerDay): static
    {
        $this->rentalPricePerDay = $rentalPricePerDay !== null ? number_format($rentalPricePerDay, 3, '.', '') : null;

        return $this;
    }

    public function getRentalDescription(): ?string
    {
        return $this->rentalDescription;
    }

    public function setRentalDescription(?string $rentalDescription): static
    {
        $this->rentalDescription = $rentalDescription;

        return $this;
    }

    public function getModerationStatus(): string
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(string $moderationStatus): static
    {
        $allowed = [
            self::MODERATION_PENDING,
            self::MODERATION_APPROVED,
            self::MODERATION_BANNED,
        ];
        if (!\in_array($moderationStatus, $allowed, true)) {
            $moderationStatus = self::MODERATION_PENDING;
        }

        $this->moderationStatus = $moderationStatus;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return isset($this->createdAt) ? $this->createdAt : null;
    }

    protected function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        if ($createdAt === null) {
            unset($this->createdAt);

            return $this;
        }

        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTime();
        }
        if ($this->moderationStatus === '') {
            $this->moderationStatus = self::MODERATION_PENDING;
        }
    }
}
