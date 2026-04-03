<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\MaintenanceRepository::class)]
#[ORM\Table(name: 'maintenance')]
class Maintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $type;

    #[ORM\Column(type: Types::STRING)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, length: 65535)]
    private string $description;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $datePlanifiee;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateReelle = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $equipementId;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userlog = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $declencheur = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $kilometragePrevu = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $heuresPrevues = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cout = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $technicien = null;

    public function getId(): int
    {
        return $this->id;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDatePlanifiee(): \DateTimeInterface
    {
        return $this->datePlanifiee;
    }

    public function setDatePlanifiee(\DateTimeInterface $datePlanifiee): static
    {
        $this->datePlanifiee = $datePlanifiee;

        return $this;
    }

    public function getDateReelle(): ?\DateTimeInterface
    {
        return $this->dateReelle;
    }

    public function setDateReelle(?\DateTimeInterface $dateReelle): static
    {
        $this->dateReelle = $dateReelle;

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

    public function getEquipementId(): int
    {
        return $this->equipementId;
    }

    public function setEquipementId(int $equipementId): static
    {
        $this->equipementId = $equipementId;

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

    public function getDeclencheur(): ?string
    {
        return $this->declencheur;
    }

    public function setDeclencheur(?string $declencheur): static
    {
        $this->declencheur = $declencheur;

        return $this;
    }

    public function getKilometragePrevu(): ?int
    {
        return $this->kilometragePrevu;
    }

    public function setKilometragePrevu(?int $kilometragePrevu): static
    {
        $this->kilometragePrevu = $kilometragePrevu;

        return $this;
    }

    public function getHeuresPrevues(): ?int
    {
        return $this->heuresPrevues;
    }

    public function setHeuresPrevues(?int $heuresPrevues): static
    {
        $this->heuresPrevues = $heuresPrevues;

        return $this;
    }

    public function getCout(): ?string
    {
        return $this->cout;
    }

    public function setCout(?string $cout): static
    {
        $this->cout = $cout;

        return $this;
    }

    public function getTechnicien(): ?string
    {
        return $this->technicien;
    }

    public function setTechnicien(?string $technicien): static
    {
        $this->technicien = $technicien;

        return $this;
    }
}