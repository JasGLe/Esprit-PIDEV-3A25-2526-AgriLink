<?php

namespace App\Entity\Forum;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\Forum\ForumRepository::class)]
#[ORM\Table(name: 'forum')]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Le titre du sujet est obligatoire.'),
        new Assert\Length(
            min: 2,
            max: 150,
            minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
            maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.'
        ),
    ])]
    private string $titre;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'La categorie est obligatoire.'),
        new Assert\Choice(
            choices: ['Agriculture generale', 'Agriculture generale', 'Elevage', 'Elevage', 'Cultures', 'Equipements', 'Equipements', 'Meteo', 'Meteo', 'Autre'],
            message: 'La categorie selectionnee est invalide.'
        ),
    ])]
    private string $categorie = '';

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre ?? '';

        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie ?? '';

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }
}
