<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Maintenance — représente une opération de maintenance planifiée ou réalisée
 * sur un équipement agricole.
 *
 * Règles métier :
 * - Une maintenance est liée à un équipement via equipementId (clé étrangère entière,
 *   non mappée en ORM pour rester compatible avec l'architecture existante).
 * - Deux types : "Préventive" (planifiée à l'avance) et "Corrective" (réaction à une panne).
 * - Une maintenance est "en retard" si sa date planifiée est passée ET son statut
 *   n'est ni "Terminée" ni "Annulée" — voir isEnRetard().
 * - Le coût est stocké en DECIMAL(10,2) pour éviter les erreurs d'arrondi sur les montants.
 * - La dateCreation est remplie automatiquement par lifecycle callback (onPrePersist).
 *
 * Relations :
 * - Plusieurs maintenances peuvent concerner un même équipement.
 * - L'accès est contrôlé dans MaintenanceController via denyAccessUnlessOwner()
 *   qui compare le userlog de l'équipement associé avec l'utilisateur connecté.
 *
 */ // Fix PHPStan — suppression des annotations PHPDoc @ORM redondantes avec les attributs PHP 8
#[ORM\Entity(repositoryClass: \App\Repository\MaintenanceRepository::class)]
#[ORM\Table(name: 'maintenance')]
#[ORM\HasLifecycleCallbacks]
class Maintenance
{
    // ════════════════════════════════════════════════════════
    // Constantes métier — utilisées dans formulaires et vues
    // ════════════════════════════════════════════════════════

    /**
     * Types de maintenances possibles.
     * Préventive = planifiée avant la panne | Corrective = réaction à une panne survenue.
     */
    const TYPES = [
        'Préventive' => 'Préventive',
        'Corrective' => 'Corrective',
    ];

    /** Catégories indiquant si la maintenance était prévue ou imprévue. */
    const CATEGORIES = [
        'Régulière' => 'Régulière',
        'Imprévue'  => 'Imprévue',
    ];

    /** Statuts du cycle de vie d'une maintenance. */
    const STATUTS = [
        'Planifiée' => 'Planifiée',
        'En cours'  => 'En cours',
        'Terminée'  => 'Terminée',
        'Annulée'   => 'Annulée',
    ];

    public function __construct()
    {
        $this->datePlanifiee = new \DateTime('today');
    }

    // ════════════════════════════════════════════════════════
    // Propriétés
    // ════════════════════════════════════════════════════════

    /**
     * Identifiant primaire auto-généré.
     * @phpstan-ignore property.onlyRead (Fix PHPStan #66 : Doctrine écrit $id via reflection lors de la persistance, jamais via setter PHP)
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * Type de maintenance : "Préventive" ou "Corrective".
     * Détermine la couleur du badge dans l'interface (bleu vs rose).
     */
    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'Le type de maintenance est obligatoire.')]
    #[Assert\Choice(
        choices: ['Préventive', 'Corrective'],
        message: 'Type invalide.'
    )]
    private string $type = '';

    /**
     * Catégorie : "Régulière" (périodique planifiée) ou "Imprévue" (urgence).
     * Permet de suivre la proportion de maintenances non prévues sur un équipement.
     */
    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(
        choices: ['Régulière', 'Imprévue'],
        message: 'Catégorie invalide.'
    )]
    private string $categorie = '';

    /**
     * Description détaillée des travaux à effectuer ou effectués.
     * Transmise au service GroqDiagnosticService pour enrichir le diagnostic IA.
     * Contrainte : minimum 10 caractères pour garantir une description exploitable.
     */
    #[ORM\Column(type: Types::TEXT, length: 65535)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $description = '';

    /**
     * Date à laquelle la maintenance est planifiée.
     * Doit être aujourd'hui ou dans le futur (contrainte métier : on ne crée pas
     * une maintenance dans le passé — on la signale via dateReelle).
     * Sert de référence pour détecter les retards via isEnRetard().
     */
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date planifiée est obligatoire.')]
    #[Assert\GreaterThanOrEqual(
        value: 'today',
        message: 'La date planifiée doit être aujourd\'hui ou dans le futur.'
    )]
    private \DateTimeInterface $datePlanifiee;

    /**
     * Date à laquelle la maintenance a réellement été effectuée (optionnel).
     * Renseignée après coup quand la maintenance est terminée.
     * Contrainte : ne peut pas être antérieure à datePlanifiee.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Expression(
        expression: "this.getDateReelle() === null or this.getDateReelle() >= this.getDatePlanifiee()",
        message: 'La date réelle ne peut pas être avant la date planifiée.'
    )]
    private ?\DateTimeInterface $dateReelle = null;

    /**
     * Statut courant dans le cycle de vie de la maintenance.
     * Valeur par défaut : "Planifiée" à la création.
     * Affecte le calcul isEnRetard() : "Terminée" et "Annulée" ne peuvent pas être en retard.
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Choice(
        choices: ['Planifiée', 'En cours', 'Terminée', 'Annulée'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = 'Planifiée';

    /**
     * Clé étrangère vers l'équipement concerné (id entier, non mappée en ORM).
     * Le contrôleur charge l'entité Equipement séparément via EquipementRepository::find().
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Veuillez choisir un équipement.')]
    #[Assert\Positive(message: 'Équipement invalide.')]
    private int $equipementId = 0;

    /**
     * Identifiant de l'utilisateur qui a créé la maintenance.
     * Utilisé pour filtrer les maintenances dans MaintenanceController::index()
     * via les ids d'équipements de l'utilisateur (indirectement via equipementId).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userlog = null;

    /** Date de création — remplie automatiquement à la première persistance. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    /** Déclencheur de la maintenance (kilométrage, heures, date) — non utilisé dans l'UI actuelle. */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $declencheur = null;

    /** Kilométrage prévu pour la prochaine maintenance kilométrique — non utilisé dans l'UI actuelle. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $kilometragePrevu = null;

    /** Heures moteur prévues pour la prochaine maintenance — non utilisé dans l'UI actuelle. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $heuresPrevues = null;

    /**
     * Coût de la maintenance en Dinars Tunisiens (DT).
     * Stocké en DECIMAL(10,2) pour la précision financière.
     * Affiché formaté (X XXX,XX TND) dans les vues. Converti en temps réel
     * vers 6 devises étrangères via ExchangeRateService.
     * Contrainte : positif ou nul, < 9 999 999.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le coût doit être positif ou nul.')]
    #[Assert\LessThan(value: 9999999, message: 'Montant trop élevé.')]
    private ?string $cout = null;

    /**
     * Nom du technicien chargé de la maintenance.
     * Optionnel — affiché dans la grille info des cartes maintenance.
     * Contrainte : uniquement des lettres, espaces et tirets.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut dépasser 100 caractères.')]
    #[Assert\Regex(pattern: '/^[\p{L} \-]+$/u', message: 'Nom invalide (lettres, espaces et tirets uniquement).')]
    private ?string $technicien = null;

    // ════════════════════════════════════════════════════════
    // Constructeur
    // ════════════════════════════════════════════════════════

    /**
     * Initialise datePlanifiee à aujourd'hui pour les nouveaux objets créés via formulaire.
     * Doctrine bypasse ce constructeur lors de l'hydration depuis la base (doctrine/instantiator),
     * donc il n'interfère pas avec les entités chargées depuis la DB.
     */
    public function __construct()
    {
        // Doctrine Doctor fix — datePlanifiee non-nullable, initialisée à today par défaut
        $this->datePlanifiee = new \DateTime('today');
    }

    // ════════════════════════════════════════════════════════
    // Lifecycle callbacks
    // ════════════════════════════════════════════════════════

    /**
     * Initialise dateCreation automatiquement avant la première insertion en base.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->dateCreation = new \DateTime();
    }

    // ════════════════════════════════════════════════════════
    // Helpers métier
    // ════════════════════════════════════════════════════════

    /**
     * Indique si la maintenance est terminée.
     *
     * @return bool true si statut === "Terminée"
     */
    public function isTerminee(): bool
    {
        return $this->statut === 'Terminée';
    }

    /**
     * Détermine si la maintenance est en retard.
     *
     * Logique métier :
     * - Une maintenance "Terminée" ou "Annulée" ne peut jamais être en retard.
     * - Pour les autres statuts : en retard si datePlanifiee < aujourd'hui.
     *
     * Appelée dans les vues Twig pour afficher le badge "⚠ En retard"
     * et dans MaintenanceRepository::countEnRetardForUser() pour les KPIs.
     *
     * @return bool true si la maintenance est active et sa date est dépassée
     */
    public function isEnRetard(): bool
    {
        if ($this->statut === 'Terminée' || $this->statut === 'Annulée') {
            return false;
        }
        return $this->datePlanifiee < new \DateTime('today'); // Fix PHPStan — $datePlanifiee est non-nullable, vérification null redondante supprimée
    }

    // ════════════════════════════════════════════════════════
    // Getters / Setters
    // ════════════════════════════════════════════════════════

    public function getId(): int { return $this->id; }

    public function getType(): string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type ?? ''; return $this; }

    public function getCategorie(): string { return $this->categorie; }
    public function setCategorie(?string $categorie): static { $this->categorie = $categorie ?? ''; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description ?? ''; return $this; }

    public function getDatePlanifiee(): \DateTimeInterface { return $this->datePlanifiee; }
    public function setDatePlanifiee(?\DateTimeInterface $datePlanifiee): static { $this->datePlanifiee = $datePlanifiee ?? new \DateTime('today'); return $this; }

    public function getDateReelle(): ?\DateTimeInterface { return $this->dateReelle; }
    public function setDateReelle(?\DateTimeInterface $dateReelle): static { $this->dateReelle = $dateReelle; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static { $this->statut = $statut; return $this; }

    public function getEquipementId(): int { return $this->equipementId; }
    public function setEquipementId(?int $equipementId): static { $this->equipementId = $equipementId ?? 0; return $this; }

    public function getUserlog(): ?int { return $this->userlog; }
    public function setUserlog(?int $userlog): static { $this->userlog = $userlog; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    // protected — dateCreation gérée par onPrePersist(), pas de setter public (Doctrine Doctor fix)
    protected function setDateCreation(?\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

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
