<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\EquipementRepository::class)]
#[ORM\Table(name: 'equipement')]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $nom;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    #[ORM\Column(type: Types::STRING)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
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

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $kilometrageActuel = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $kilometrageDerniereMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $seuilKmMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $heuresUtilisation = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $heuresDerniereMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $seuilHeuresMaintenance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $seuilJoursMaintenance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDerniereMaintenance = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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