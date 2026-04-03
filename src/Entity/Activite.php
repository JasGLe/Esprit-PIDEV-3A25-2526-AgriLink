<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $idActivite;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $titre;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $typeActivite;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $statut;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $coutEstime = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $idAgriculteur;

    public function getIdActivite(): int
    {
        return $this->idActivite;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getTypeActivite(): string
    {
        return $this->typeActivite;
    }

    public function setTypeActivite(string $typeActivite): static
    {
        $this->typeActivite = $typeActivite;

        return $this;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCoutEstime(): ?string
    {
        return $this->coutEstime;
    }

    public function setCoutEstime(?string $coutEstime): static
    {
        $this->coutEstime = $coutEstime;

        return $this;
    }

    public function getIdAgriculteur(): int
    {
        return $this->idAgriculteur;
    }

    public function setIdAgriculteur(int $idAgriculteur): static
    {
        $this->idAgriculteur = $idAgriculteur;

        return $this;
    }
}