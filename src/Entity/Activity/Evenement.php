<?php

namespace App\Entity\Activity;

use App\Entity\UserManagement\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: \App\Repository\Activity\EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
#[Assert\Callback('validateDateEvenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $idEvenement;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $titre;

    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Choice(
        choices: ['OFFICIEL', 'PERSONNEL'],
        message: 'Veuillez choisir un type valide.'
    )]
    private string $typeEvenement;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $dateEvenement = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $lieu;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'organisateur_id', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?User $organisateur = null;

    public function getId(): int
    {
        return $this->idEvenement;
    }

    public function getIdEvenement(): int
    {
        return $this->idEvenement;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTypeEvenement(): string
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(string $typeEvenement): static
    {
        $this->typeEvenement = $typeEvenement;

        return $this;
    }

    public function getDateEvenement(): ?\DateTimeInterface
    {
        return $this->dateEvenement;
    }

    public function setDateEvenement(?\DateTimeInterface $dateEvenement): static
    {
        $this->dateEvenement = $dateEvenement;

        return $this;
    }

    public function getLieu(): string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getIdOrganisateur(): ?int
    {
        return $this->organisateur?->getId();
    }

    public function setIdOrganisateur(?int $idOrganisateur): static
    {
        // Keep backward compatibility for existing code paths.
        if ($idOrganisateur === null) {
            $this->organisateur = null;
        }

        return $this;
    }

    public function getOrganisateur(): ?User
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?User $organisateur): static
    {
        $this->organisateur = $organisateur;

        return $this;
    }

    public function validateDateEvenement(ExecutionContextInterface $context, $payload): void
    {
        if ($this->dateEvenement !== null) {
            $today = new \DateTime('today');
            if ($this->dateEvenement < $today) {
                $context->buildViolation('La date de l\'événement ne peut pas être dans le passé.')
                    ->atPath('dateEvenement')
                    ->addViolation();
            }
        }
    }
}
