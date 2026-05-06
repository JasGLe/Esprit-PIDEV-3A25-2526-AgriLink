<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Legacy class — superseded by App\Entity\Marketplace\Panier.
// ORM attributes removed to prevent duplicate 'panier' table mapping in SchemaTool.
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $idPersonne = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $idProduit = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $nomProduit = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantite;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $prixTotal = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAjout = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdPersonne(): ?int
    {
        return $this->idPersonne;
    }

    public function setIdPersonne(?int $idPersonne): static
    {
        $this->idPersonne = $idPersonne;

        return $this;
    }

    public function getIdProduit(): ?int
    {
        return $this->idProduit;
    }

    public function setIdProduit(?int $idProduit): static
    {
        $this->idProduit = $idProduit;

        return $this;
    }

    public function getNomProduit(): ?string
    {
        return $this->nomProduit;
    }

    public function setNomProduit(?string $nomProduit): static
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

    public function getPrixTotal(): ?float
    {
        return $this->prixTotal !== null ? (float) $this->prixTotal : null;
    }

    public function setPrixTotal(?float $prixTotal): static
    {
        $this->prixTotal = $prixTotal !== null ? number_format($prixTotal, 3, '.', '') : null;

        return $this;
    }

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(?\DateTimeInterface $dateAjout): static
    {
        $this->dateAjout = $dateAjout;

        return $this;
    }
}
