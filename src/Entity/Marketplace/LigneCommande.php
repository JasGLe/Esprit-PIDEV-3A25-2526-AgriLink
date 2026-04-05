<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\LigneCommandeRepository::class)]
#[ORM\Table(name: 'ligne_commande')]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $idCommande;

    #[ORM\Column(type: Types::INTEGER)]
    private int $idProduit;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $idFournisseur = null;

    #[ORM\Column(type: Types::STRING)]
    private string $nomProduit;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantite;

    #[ORM\Column(type: Types::FLOAT)]
    private float $prixUnitaire;

    #[ORM\Column(type: Types::FLOAT)]
    private float $prixTotal;

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdCommande(): int
    {
        return $this->idCommande;
    }

    public function setIdCommande(int $idCommande): static
    {
        $this->idCommande = $idCommande;

        return $this;
    }

    public function getIdProduit(): int
    {
        return $this->idProduit;
    }

    public function setIdProduit(int $idProduit): static
    {
        $this->idProduit = $idProduit;

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

    public function getNomProduit(): string
    {
        return $this->nomProduit;
    }

    public function setNomProduit(string $nomProduit): static
    {
        $this->nomProduit = $nomProduit;

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

    public function getPrixUnitaire(): float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getPrixTotal(): float
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(float $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

        return $this;
    }
}