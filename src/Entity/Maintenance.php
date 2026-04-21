<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\MaintenanceRepository::class)]
#[ORM\Table(name: 'maintenance')]
#[ORM\HasLifecycleCallbacks]
class Maintenance
{
    // ════════════════════════════════════════════════════════
    // Constantes métier
    // ════════════════════════════════════════════════════════

    const TYPES = [
        'Préventive' => 'Préventive',
        'Corrective' => 'Corrective',
    ];

    const CATEGORIES = [
        'Régulière' => 'Régulière',
        'Imprévue'  => 'Imprévue',
    ];

    const STATUTS = [
        'Planifiée' => 'Planifiée',
        'En cours'  => 'En cours',
        'Terminée'  => 'Terminée',
        'Annulée'   => 'Annulée',
    ];

    // ════════════════════════════════════════════════════════
    // Champs — Assert ajoutés, rien supprimé
    // ════════════════════════════════════════════════════════

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'Le type de maintenance est obligatoire.')]
    #[Assert\Choice(
        choices: ['Préventive', 'Corrective'],
        message: 'Type invalide.'
    )]
    private ?string $type = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(
        choices: ['Régulière', 'Imprévue'],
        message: 'Catégorie invalide.'
    )]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, length: 65535)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date planifiée est obligatoire.')]
    #[Assert\GreaterThanOrEqual(
        value: 'today',
        message: 'La date planifiée doit être aujourd\'hui ou dans le futur.'
    )]
    private ?\DateTimeInterface $datePlanifiee = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Expression(
        expression: "this.getDateReelle() === null or this.getDateReelle() >= this.getDatePlanifiee()",
        message: 'La date réelle ne peut pas être avant la date planifiée.'
    )]
    private ?\DateTimeInterface $dateReelle = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Choice(
        choices: ['Planifiée', 'En cours', 'Terminée', 'Annulée'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = 'Planifiée';

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Veuillez choisir un équipement.')]
    #[Assert\Positive(message: 'Équipement invalide.')]
    private ?int $equipementId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userlog = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    // Champs ignorés pour l'instant — inchangés
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $declencheur = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $kilometragePrevu = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $heuresPrevues = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le coût doit être positif ou nul.')]
    #[Assert\LessThan(value: 9999999, message: 'Montant trop élevé.')]
    private ?string $cout = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut dépasser 100 caractères.')]
    #[Assert\Regex(pattern: '/^[\p{L} \-]+$/u', message: 'Nom invalide (lettres, espaces et tirets uniquement).')]
    private ?string $technicien = null;

    // ════════════════════════════════════════════════════════
    // Lifecycle
    // ════════════════════════════════════════════════════════

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->dateCreation = new \DateTime();
    }

    // ════════════════════════════════════════════════════════
    // Helpers métier
    // ════════════════════════════════════════════════════════

    public function isTerminee(): bool
    {
        return $this->statut === 'Terminée';
    }

    public function isEnRetard(): bool
    {
        if ($this->statut === 'Terminée' || $this->statut === 'Annulée') {
            return false;
        }
        return $this->datePlanifiee !== null
            && $this->datePlanifiee < new \DateTime('today');
    }

    // ════════════════════════════════════════════════════════
    // Getters / Setters — INCHANGÉS + nullable fixes
    // ════════════════════════════════════════════════════════

    public function getId(): int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getDatePlanifiee(): ?\DateTimeInterface { return $this->datePlanifiee; }
    public function setDatePlanifiee(?\DateTimeInterface $datePlanifiee): static { $this->datePlanifiee = $datePlanifiee; return $this; }

    public function getDateReelle(): ?\DateTimeInterface { return $this->dateReelle; }
    public function setDateReelle(?\DateTimeInterface $dateReelle): static { $this->dateReelle = $dateReelle; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static { $this->statut = $statut; return $this; }

    public function getEquipementId(): ?int { return $this->equipementId; }
    public function setEquipementId(?int $equipementId): static { $this->equipementId = $equipementId; return $this; }

    public function getUserlog(): ?int { return $this->userlog; }
    public function setUserlog(?int $userlog): static { $this->userlog = $userlog; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(?\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getDeclencheur(): ?string { return $this->declencheur; }
    public function setDeclencheur(?string $declencheur): static { $this->declencheur = $declencheur; return $this; }

    public function getKilometragePrevu(): ?int { return $this->kilometragePrevu; }
    public function setKilometragePrevu(?int $kilometragePrevu): static { $this->kilometragePrevu = $kilometragePrevu; return $this; }

    public function getHeuresPrevues(): ?int { return $this->heuresPrevues; }
    public function setHeuresPrevues(?int $heuresPrevues): static { $this->heuresPrevues = $heuresPrevues; return $this; }

    public function getCout(): ?string { return $this->cout; }
    public function setCout(?string $cout): static { $this->cout = $cout; return $this; }

    public function getTechnicien(): ?string { return $this->technicien; }
    public function setTechnicien(?string $technicien): static { $this->technicien = $technicien; return $this; }
}