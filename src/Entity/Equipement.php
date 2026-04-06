<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;  // ← AJOUT

#[ORM\Entity(repositoryClass: \App\Repository\EquipementRepository::class)]
#[ORM\Table(name: 'equipement')]
#[ORM\HasLifecycleCallbacks]  // ← AJOUT : pour PrePersist/PreUpdate
class Equipement
{
    // ════════════════════════════════════════════════════════
    // AJOUT : Constantes métier
    // ════════════════════════════════════════════════════════

    const CATEGORIES = [
        'Véhicule Motorisé' => 'Véhicule Motorisé',
        'Autre Équipement'  => 'Autre Équipement',
    ];

    const TYPES_VEHICULES = [
        'Voiture'  => 'Voiture',
        'Camion'   => 'Camion',
        'Tracteur' => 'Tracteur',
        'Semoir'   => 'Semoir',   // semoir peut être tracté
    ];

    const TYPES_EQUIPEMENTS = [
        'Pulvérisateur' => 'Pulvérisateur',
        'Moissonneuse'  => 'Moissonneuse',
        'Irrigation'    => 'Irrigation',
        'Charrue'       => 'Charrue',
        'Semoir'        => 'Semoir',
        'Autre'         => 'Autre',
    ];

    const STATUTS = [
        'Actif'          => 'Actif',
        'En panne'       => 'En panne',
        'En maintenance' => 'En maintenance',
        'Hors service'   => 'Hors service',
    ];

    // ════════════════════════════════════════════════════════
    // Champs existants — on ajoute seulement les Assert
    // ════════════════════════════════════════════════════════

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2, max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}0-9 \-_]+$/u',
        message: 'Le nom contient des caractères non autorisés.'
    )]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    private ?string $type = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(
        choices: ['Véhicule Motorisé', 'Autre Équipement'],
        message: 'Catégorie invalide.'
    )]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'La marque ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $marque = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le modèle ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $modele = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual(
        value: 'today',
        message: "La date d'acquisition ne peut pas être dans le futur."
    )]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Choice(
        choices: ['Actif', 'En panne', 'En maintenance', 'Hors service'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $exploitationId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userlog = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    // ── Champs véhicule : Assert\When → actifs UNIQUEMENT si Véhicule Motorisé ──

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\NotBlank(message: 'Le kilométrage actuel est obligatoire pour un véhicule.'),
            new Assert\PositiveOrZero(message: 'Le kilométrage doit être positif ou nul.'),
            new Assert\LessThan(
                value: 10000000,
                message: 'Le kilométrage semble invalide (trop élevé).'
            ),
        ]
    )]
    private ?int $kilometrageActuel = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'La valeur doit être positive ou nulle.'),
        ]
    )]
    private ?int $kilometrageDerniereMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\NotBlank(message: 'Le seuil kilométrique est obligatoire pour un véhicule.'),
            new Assert\Positive(message: 'Le seuil km doit être supérieur à 0.'),
        ]
    )]
    private ?int $seuilKmMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'Les heures doivent être positives ou nulles.'),
        ]
    )]
    private ?int $heuresUtilisation = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'La valeur doit être positive ou nulle.'),
        ]
    )]
    private ?int $heuresDerniereMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\NotBlank(message: 'Le seuil en heures est obligatoire pour un véhicule.'),
            new Assert\Positive(message: 'Le seuil heures doit être supérieur à 0.'),
        ]
    )]
    private ?int $seuilHeuresMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le seuil en jours doit être supérieur à 0.')]
    private ?int $seuilJoursMaintenance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual(
        value: 'today',
        message: 'La date de dernière maintenance ne peut pas être dans le futur.'
    )]
    private ?\DateTimeInterface $dateDerniereMaintenance = null;

    // ════════════════════════════════════════════════════════
    // AJOUT : Lifecycle callbacks (dates automatiques)
    // ════════════════════════════════════════════════════════

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->dateCreation = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->dateModification = new \DateTime();
    }

    // ════════════════════════════════════════════════════════
    // AJOUT : Helper métier
    // ════════════════════════════════════════════════════════

    public function isVehicule(): bool
    {
        return $this->categorie === 'Véhicule Motorisé';
    }

    // ════════════════════════════════════════════════════════
    // Getters / Setters existants — INCHANGÉS
    // ════════════════════════════════════════════════════════

    public function getId(): int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    // APRÈS
public function getCategorie(): ?string
{
    return $this->categorie;
}

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque;
        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;
        return $this;
    }

    public function getDateAcquisition(): ?\DateTimeInterface
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTimeInterface $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getExploitationId(): ?int
    {
        return $this->exploitationId;
    }

    public function setExploitationId(?int $exploitationId): static
    {
        $this->exploitationId = $exploitationId;
        return $this;
    }

    public function getUserlog(): ?int
    {
        return $this->userlog;
    }

    public function setUserlog(?int $userlog): static
    {
        $this->userlog = $userlog;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): static
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    public function getKilometrageActuel(): ?int
    {
        return $this->kilometrageActuel;
    }

    public function setKilometrageActuel(?int $kilometrageActuel): static
    {
        $this->kilometrageActuel = $kilometrageActuel;
        return $this;
    }

    public function getKilometrageDerniereMaintenance(): ?int
    {
        return $this->kilometrageDerniereMaintenance;
    }

    public function setKilometrageDerniereMaintenance(?int $kilometrageDerniereMaintenance): static
    {
        $this->kilometrageDerniereMaintenance = $kilometrageDerniereMaintenance;
        return $this;
    }

    public function getSeuilKmMaintenance(): ?int
    {
        return $this->seuilKmMaintenance;
    }

    public function setSeuilKmMaintenance(?int $seuilKmMaintenance): static
    {
        $this->seuilKmMaintenance = $seuilKmMaintenance;
        return $this;
    }

    public function getHeuresUtilisation(): ?int
    {
        return $this->heuresUtilisation;
    }

    public function setHeuresUtilisation(?int $heuresUtilisation): static
    {
        $this->heuresUtilisation = $heuresUtilisation;
        return $this;
    }

    public function getHeuresDerniereMaintenance(): ?int
    {
        return $this->heuresDerniereMaintenance;
    }

    public function setHeuresDerniereMaintenance(?int $heuresDerniereMaintenance): static
    {
        $this->heuresDerniereMaintenance = $heuresDerniereMaintenance;
        return $this;
    }

    public function getSeuilHeuresMaintenance(): ?int
    {
        return $this->seuilHeuresMaintenance;
    }

    public function setSeuilHeuresMaintenance(?int $seuilHeuresMaintenance): static
    {
        $this->seuilHeuresMaintenance = $seuilHeuresMaintenance;
        return $this;
    }

    public function getSeuilJoursMaintenance(): ?int
    {
        return $this->seuilJoursMaintenance;
    }

    public function setSeuilJoursMaintenance(?int $seuilJoursMaintenance): static
    {
        $this->seuilJoursMaintenance = $seuilJoursMaintenance;
        return $this;
    }

    public function getDateDerniereMaintenance(): ?\DateTimeInterface
    {
        return $this->dateDerniereMaintenance;
    }

    public function setDateDerniereMaintenance(?\DateTimeInterface $dateDerniereMaintenance): static
    {
        $this->dateDerniereMaintenance = $dateDerniereMaintenance;
        return $this;
    }
}