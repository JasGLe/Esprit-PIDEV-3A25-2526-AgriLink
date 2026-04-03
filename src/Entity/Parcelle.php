<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ParcelleRepository::class)]
#[ORM\Table(name: 'parcelle')]
class Parcelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Exploitation::class, inversedBy: 'parcelles')]
    #[ORM\JoinColumn(name: 'exploitation_id', nullable: false)]
    private Exploitation $exploitation;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $nom;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $superficie = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $typeSol = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $etat = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getExploitation(): Exploitation
    {
        return $this->exploitation;
    }

    public function setExploitation(Exploitation $exploitation): static
    {
        $this->exploitation = $exploitation;

        return $this;
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

    public function getSuperficie(): ?float
    {
        return $this->superficie;
    }

    public function setSuperficie(?float $superficie): static
    {
        $this->superficie = $superficie;

        return $this;
    }

    public function getTypeSol(): ?string
    {
        return $this->typeSol;
    }

    public function setTypeSol(?string $typeSol): static
    {
        $this->typeSol = $typeSol;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }
}