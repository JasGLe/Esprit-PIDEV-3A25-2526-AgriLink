<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\ProduitsRepository::class)]
#[ORM\Table(name: 'produits')]
class Produits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $nom;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $categorie;

    #[ORM\Column(name: 'prixUnitaire', type: Types::FLOAT)]
    private float $prixUnitaire;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $image;

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
    private ?bool $active = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantite = null;

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
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
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
}
