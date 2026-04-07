<?php

namespace App\Entity\Annonce;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\Annonce\AnnonceRepository::class)]
#[ORM\Table(name: 'annonce')]
class Annonce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotBlank(message: 'Le type de produit est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 80,
        minMessage: 'Le type doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le type ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\NotBlank(message: 'La quantite est obligatoire.')]
    #[Assert\Positive(message: 'La quantite doit etre superieure a zero.')]
    #[Assert\LessThanOrEqual(
        value: 1000000,
        message: 'La quantite ne doit pas depasser {{ compared_value }}.'
    )]
    private ?int $quantite = null;

    #[ORM\Column(name: 'prixUnitaire', type: Types::FLOAT, nullable: true)]
    #[Assert\NotBlank(message: 'Le prix unitaire est obligatoire.')]
    #[Assert\Positive(message: 'Le prix unitaire doit etre superieur a zero.')]
    #[Assert\LessThanOrEqual(
        value: 1000000,
        message: 'Le prix unitaire ne doit pas depasser {{ compared_value }} TND.'
    )]
    private ?float $prixUnitaire = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['ACTIVE', 'INACTIVE', 'VENDUE'],
        message: 'Le statut selectionne est invalide.'
    )]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'L URL ne doit pas depasser {{ limit }} caracteres.'
    )]
    #[Assert\Url(
        protocols: ['http', 'https'],
        message: 'Veuillez saisir une URL valide pour la photo.'
    )]
    private ?string $photos = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre === '' ? null : $titre;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type === '' ? null : $type;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): ?float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(?float $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status === '' ? null : $status;
        return $this;
    }

    public function getPhotos(): ?string
    {
        return $this->photos;
    }

    public function setPhotos(?string $photos): static
    {
        $photos = $photos !== null ? trim($photos) : null;
        $this->photos = $photos === '' ? null : $photos;
        return $this;
    }
}