<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Equipement — représente un équipement agricole appartenant à un agriculteur.
 *
 * Règles métier :
 * - Un équipement appartient à un unique utilisateur (userlog = id user).
 * - Deux catégories possibles : "Véhicule Motorisé" et "Autre Équipement".
 *   Les champs kilométrage/heures ne sont pertinents QUE pour les véhicules.
 * - Les contraintes Assert\When sur les champs véhicule évitent une validation
 *   côté serveur inutile quand la catégorie est "Autre Équipement".
 * - Un code passeport unique (format EQ-YYYY-XXXX) est généré une seule fois
 *   et reste immuable ; il est encodé dans un QR code.
 * - Les champs latitude/longitude permettent la géolocalisation sur carte Leaflet.
 *
 * Relations :
 * - Un équipement peut avoir plusieurs maintenances (relation via equipementId dans Maintenance).
 * - L'exploitationId est une clé étrangère vers l'entité exploitation (non mappée en ORM ici).
 *
 * @ORM\Entity(repositoryClass: \App\Repository\EquipementRepository::class)
 * @ORM\Table(name: "equipement")
 * @ORM\HasLifecycleCallbacks
 */
#[ORM\Entity(repositoryClass: \App\Repository\EquipementRepository::class)]
#[ORM\Table(name: 'equipement')]
#[ORM\HasLifecycleCallbacks]
class Equipement
{
    // ════════════════════════════════════════════════════════
    // Constantes métier — utilisées dans les formulaires et validations
    // ════════════════════════════════════════════════════════

    /** Catégories d'équipements acceptées par le système. */
    const CATEGORIES = [
        'Véhicule Motorisé' => 'Véhicule Motorisé',
        'Autre Équipement'  => 'Autre Équipement',
    ];

    /** Types valides pour la catégorie "Véhicule Motorisé". */
    const TYPES_VEHICULES = [
        'Voiture'  => 'Voiture',
        'Camion'   => 'Camion',
        'Tracteur' => 'Tracteur',
        'Semoir'   => 'Semoir',   // le semoir peut être tracté, donc classé ici aussi
    ];

    /** Types valides pour la catégorie "Autre Équipement". */
    const TYPES_EQUIPEMENTS = [
        'Pulvérisateur' => 'Pulvérisateur',
        'Moissonneuse'  => 'Moissonneuse',
        'Irrigation'    => 'Irrigation',
        'Charrue'       => 'Charrue',
        'Semoir'        => 'Semoir',
        'Autre'         => 'Autre',
    ];

    /** Statuts opérationnels d'un équipement. */
    const STATUTS = [
        'Actif'          => 'Actif',
        'En panne'       => 'En panne',
        'En maintenance' => 'En maintenance',
        'Hors service'   => 'Hors service',
    ];

    // ════════════════════════════════════════════════════════
    // Propriétés — champs communs à tous les équipements
    // ════════════════════════════════════════════════════════

    /** Identifiant primaire auto-généré. */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * Nom de l'équipement — identifiant humain affiché dans toute l'interface.
     * Contrainte : 2–100 caractères, lettres/chiffres/tirets/espaces/underscores uniquement.
     */
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
    // string non-nullable — aligné sur la colonne NOT NULL en base (Doctrine Doctor fix)
    private string $nom = '';

    /**
     * Type précis de l'équipement (ex: Tracteur, Pulvérisateur).
     * Dépend de la catégorie : alimenté dynamiquement via AJAX dans le formulaire.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    // string non-nullable — aligné sur la colonne NOT NULL en base (Doctrine Doctor fix)
    private string $type = '';

    /**
     * Catégorie principale : "Véhicule Motorisé" ou "Autre Équipement".
     * Détermine l'affichage des champs kilométrage/heures dans le formulaire.
     */
    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(
        choices: ['Véhicule Motorisé', 'Autre Équipement'],
        message: 'Catégorie invalide.'
    )]
    // string non-nullable — aligné sur la colonne NOT NULL en base (Doctrine Doctor fix)
    private string $categorie = '';

    /** Marque du fabricant (optionnel) — utilisée dans le diagnostic IA. */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'La marque ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $marque = null;

    /** Modèle commercial (optionnel) — complète la marque pour le diagnostic IA. */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le modèle ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $modele = null;

    /**
     * Date d'acquisition — permet de calculer l'âge de l'équipement.
     * L'âge est transmis au service GroqDiagnosticService pour enrichir le diagnostic.
     * Contrainte : ne peut pas être dans le futur.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual(
        value: 'today',
        message: "La date d'acquisition ne peut pas être dans le futur."
    )]
    private ?\DateTimeInterface $dateAcquisition = null;

    /**
     * Statut opérationnel actuel — affiché dans les listes et le passeport.
     * Déclenche une notification SMS via Twilio si modifié dans le formulaire edit.
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Choice(
        choices: ['Actif', 'En panne', 'En maintenance', 'Hors service'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = null;

    /** Description libre — transmise au service IA pour enrichir le diagnostic. */
    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    /**
     * Chemin relatif de l'image stockée dans public/uploads/equipements/.
     * Format variable en base selon l'historique : "equipements/fichier.jpg"
     * ou "uploads/equipements/fichier.jpg". Les templates utilisent |split('/')|last
     * pour extraire uniquement le nom de fichier, indépendamment du format.
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $imageUrl = null;

    /** Identifiant de l'exploitation agricole (clé étrangère non mappée en ORM). */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $exploitationId = null;

    /**
     * Identifiant de l'utilisateur propriétaire de l'équipement.
     * Utilisé pour filtrer les équipements par agriculteur et
     * vérifier les droits d'accès dans denyAccessUnlessOwner().
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userlog = null;

    /** Date de création — remplie automatiquement par le lifecycle callback onPrePersist(). */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    /** Date de dernière modification — remplie par onPreUpdate(). */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    // ════════════════════════════════════════════════════════
    // Champs spécifiques véhicule — actifs UNIQUEMENT si catégorie = "Véhicule Motorisé"
    // (Assert\When évite leur validation pour les autres catégories)
    // ════════════════════════════════════════════════════════

    /**
     * Kilométrage actuel du véhicule.
     * Comparé au seuil pour détecter si une maintenance kilométrique est due.
     * Obligatoire pour les véhicules (Assert\When).
     */
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

    /**
     * Kilométrage enregistré lors de la dernière maintenance.
     * Permet de calculer les km parcourus depuis la dernière intervention.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'La valeur doit être positive ou nulle.'),
        ]
    )]
    private ?int $kilometrageDerniereMaintenance = null;

    /**
     * Seuil kilométrique déclenchant une alerte de maintenance (défaut : 10 000 km).
     * Si kilometrageActuel >= seuilKmMaintenance, une alerte est générée dans le diagnostic IA.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\NotBlank(message: 'Le seuil kilométrique est obligatoire pour un véhicule.'),
            new Assert\Positive(message: 'Le seuil km doit être supérieur à 0.'),
        ]
    )]
    private ?int $seuilKmMaintenance = null;

    /**
     * Heures d'utilisation cumulées du moteur.
     * Indicateur clé pour les tracteurs et engins dont la maintenance dépend du temps moteur.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'Les heures doivent être positives ou nulles.'),
        ]
    )]
    private ?int $heuresUtilisation = null;

    /** Heures moteur enregistrées lors de la dernière maintenance. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\PositiveOrZero(message: 'La valeur doit être positive ou nulle.'),
        ]
    )]
    private ?int $heuresDerniereMaintenance = null;

    /**
     * Seuil en heures déclenchant une alerte de maintenance (défaut : 200 h).
     * Si heuresUtilisation >= seuilHeuresMaintenance, alerte dans le diagnostic IA.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\When(
        expression: "this.getCategorie() === 'Véhicule Motorisé'",
        constraints: [
            new Assert\NotBlank(message: 'Le seuil en heures est obligatoire pour un véhicule.'),
            new Assert\Positive(message: 'Le seuil heures doit être supérieur à 0.'),
        ]
    )]
    private ?int $seuilHeuresMaintenance = null;

    /**
     * Seuil en jours entre deux maintenances périodiques (défaut : 365 j).
     * Applicable à tous les types d'équipements (pas uniquement les véhicules).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le seuil en jours doit être supérieur à 0.')]
    private ?int $seuilJoursMaintenance = null;

    /**
     * Date de la dernière maintenance effectuée sur cet équipement.
     * Utilisée avec seuilJoursMaintenance pour calculer le retard potentiel.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual(
        value: 'today',
        message: 'La date de dernière maintenance ne peut pas être dans le futur.'
    )]
    private ?\DateTimeInterface $dateDerniereMaintenance = null;

    /**
     * Code passeport unique au format EQ-YYYY-XXXX.
     * Généré une seule fois lors de la première consultation du passeport ou du QR.
     * Encodé dans le QR code SVG généré par PasseportController::qr().
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, unique: true)]
    private ?string $codePasseport = null;

    /**
     * Latitude GPS de l'emplacement de l'équipement.
     * Remplie par le formulaire via Leaflet.js (clic sur la carte ou géolocalisation navigateur).
     * Contrainte : entre -90 et 90 degrés.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(
        min: -90, max: 90,
        notInRangeMessage: 'Latitude invalide (entre -90 et 90).'
    )]
    private ?float $latitude = null;

    /**
     * Longitude GPS de l'emplacement de l'équipement.
     * Contrainte : entre -180 et 180 degrés.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(
        min: -180, max: 180,
        notInRangeMessage: 'Longitude invalide (entre -180 et 180).'
    )]
    private ?float $longitude = null;

    // ════════════════════════════════════════════════════════
    // Lifecycle callbacks — dates automatiques
    // ════════════════════════════════════════════════════════

    /**
     * Rempli dateCreation automatiquement avant toute insertion en base.
     * L'annotation #[HasLifecycleCallbacks] sur la classe est requise.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->dateCreation = new \DateTime();
    }

    /**
     * Met à jour dateModification automatiquement avant toute modification en base.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->dateModification = new \DateTime();
    }

    // ════════════════════════════════════════════════════════
    // Helpers métier
    // ════════════════════════════════════════════════════════

    /**
     * Indique si l'équipement est un véhicule motorisé.
     * Logique métier : utilisé dans le formulaire pour afficher/masquer les champs
     * kilométrage/heures, et dans le controller pour nullifier ces champs si la
     * catégorie est changée vers "Autre Équipement".
     *
     * @return bool true si la catégorie est "Véhicule Motorisé"
     */
    public function isVehicule(): bool
    {
        return $this->categorie === 'Véhicule Motorisé';
    }

    // ════════════════════════════════════════════════════════
    // Getters / Setters
    // ════════════════════════════════════════════════════════

    public function getId(): int
    {
        return $this->id;
    }

    // Retour string non-nullable — aligné sur la propriété (Doctrine Doctor fix)
    public function getNom(): string
    {
        return $this->nom;
    }

    // Paramètre ?string conservé pour compatibilité Symfony forms (null coercé en '')
    public function setNom(?string $nom): static
    {
        $this->nom = $nom ?? '';
        return $this;
    }

    // Retour string non-nullable — aligné sur la propriété (Doctrine Doctor fix)
    public function getType(): string
    {
        return $this->type;
    }

    // Paramètre ?string conservé pour compatibilité Symfony forms (null coercé en '')
    public function setType(?string $type): static
    {
        $this->type = $type ?? '';
        return $this;
    }

    // Retour string non-nullable — aligné sur la propriété (Doctrine Doctor fix)
    public function getCategorie(): string
    {
        return $this->categorie;
    }

    // Paramètre ?string conservé pour compatibilité Symfony forms (null coercé en '')
    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie ?? '';
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

    // protected — dateCreation gérée par onPrePersist(), pas de setter public (Doctrine Doctor fix)
    protected function setDateCreation(?\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    // protected — dateModification gérée par onPreUpdate(), pas de setter public (Doctrine Doctor fix)
    protected function setDateModification(?\DateTimeInterface $dateModification): static
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

    public function getCodePasseport(): ?string { return $this->codePasseport; }

    public function setCodePasseport(?string $codePasseport): static
    {
        $this->codePasseport = $codePasseport;
        return $this;
    }

    /**
     * Génère un code passeport unique basé sur l'id et l'année courante.
     * Format : EQ-2024-0042. Appelé une seule fois dans PasseportController
     * quand codePasseport est null (première consultation du passeport ou du QR).
     *
     * @return string Le code généré (non persisté automatiquement — il faut appeler em->flush())
     */
    public function genererCodePasseport(): string
    {
        return 'EQ-' . date('Y') . '-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): static { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): static { $this->longitude = $longitude; return $this; }
}
