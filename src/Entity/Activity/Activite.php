<?php

namespace App\Entity\Activity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: \App\Repository\Activity\ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
#[Assert\Callback('validateDates')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $idActivite;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $titre;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Choice(
        choices: ['SEMIS', 'IRRIGATION', 'RECOLTE', 'TRAITEMENT', 'TAILLE', 'AUTRE'],
        message: 'Veuillez choisir un type valide parmi les options proposées.'
    )]
    #[Assert\Length(max: 50)]
    private string $typeActivite;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Choice(
        choices: ['PLANIFIEE', 'EN_COURS', 'TERMINEE'],
        message: 'Veuillez choisir un statut valide.'
    )]
    #[Assert\Length(max: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le coût doit être positif ou zéro.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le coût doit être un nombre valide avec maximum 2 décimales.'
    )]
    private ?string $coutEstime = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $idAgriculteur = null;

    public function getId(): int
    {
        return $this->idActivite;
    }

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

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
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

    public function getIdAgriculteur(): ?int
    {
        return $this->idAgriculteur;
    }

    public function setIdAgriculteur(?int $idAgriculteur): static
    {
        $this->idAgriculteur = $idAgriculteur;

        return $this;
    }

    public function validateDates(ExecutionContextInterface $context, $payload): void
    {
        // Check if dateDebut is not in the past
        if ($this->dateDebut !== null) {
            $today = new \DateTime('today');
            if ($this->dateDebut < $today) {
                $context->buildViolation('La date de début ne peut pas être dans le passé.')
                    ->atPath('dateDebut')
                    ->addViolation();
            }
        }

        // Check if dateFin is after dateDebut
        if ($this->dateFin !== null && $this->dateDebut !== null) {
            if ($this->dateFin <= $this->dateDebut) {
                $context->buildViolation('La date de fin doit être après la date de début.')
                    ->atPath('dateFin')
                    ->addViolation();
            }
        }
    }
}