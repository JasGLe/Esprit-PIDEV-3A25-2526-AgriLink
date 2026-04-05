<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\CommandesRepository::class)]
#[ORM\Table(name: 'commandes')]
class Commandes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /** Colonnes camelCase héritées de la table SQL (naming strategy underscore sinon). */
    #[ORM\Column(name: 'numCommande', type: Types::STRING, length: 50)]
    private string $numCommande;

    #[ORM\Column(name: 'dateCommande', type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateCommande;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantite;

    #[ORM\Column(name: 'prixTotal', type: Types::FLOAT)]
    private float $prixTotal;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $complement = null;

    #[ORM\Column(name: 'codePostal', type: Types::STRING, length: 20, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $idFournisseur = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getNumCommande(): string
    {
        return $this->numCommande;
    }

    public function setNumCommande(string $numCommande): static
    {
        $this->numCommande = $numCommande;

        return $this;
    }

    public function getDateCommande(): \DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): static
    {
        $this->dateCommande = $dateCommande;

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

    public function getPrixTotal(): float
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(float $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getComplement(): ?string
    {
        return $this->complement;
    }

    public function setComplement(?string $complement): static
    {
        $this->complement = $complement;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

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
}