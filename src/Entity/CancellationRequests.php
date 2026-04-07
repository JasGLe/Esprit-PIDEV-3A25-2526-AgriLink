<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\CancellationRequestsRepository::class)]
#[ORM\Table(name: 'cancellation_requests')]
class CancellationRequests
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $commandeId;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $numCommande = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $fournisseurId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $requestedByUserId = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $requestedByEmail = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $requestedByName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $requestedAt;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $status = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $handledByUserId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $handledAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCommandeId(): int
    {
        return $this->commandeId;
    }

    public function setCommandeId(int $commandeId): static
    {
        $this->commandeId = $commandeId;

        return $this;
    }

    public function getNumCommande(): ?string
    {
        return $this->numCommande;
    }

    public function setNumCommande(?string $numCommande): static
    {
        $this->numCommande = $numCommande;

        return $this;
    }

    public function getFournisseurId(): ?int
    {
        return $this->fournisseurId;
    }

    public function setFournisseurId(?int $fournisseurId): static
    {
        $this->fournisseurId = $fournisseurId;

        return $this;
    }

    public function getRequestedByUserId(): ?int
    {
        return $this->requestedByUserId;
    }

    public function setRequestedByUserId(?int $requestedByUserId): static
    {
        $this->requestedByUserId = $requestedByUserId;

        return $this;
    }

    public function getRequestedByEmail(): ?string
    {
        return $this->requestedByEmail;
    }

    public function setRequestedByEmail(?string $requestedByEmail): static
    {
        $this->requestedByEmail = $requestedByEmail;

        return $this;
    }

    public function getRequestedByName(): ?string
    {
        return $this->requestedByName;
    }

    public function setRequestedByName(?string $requestedByName): static
    {
        $this->requestedByName = $requestedByName;

        return $this;
    }

    public function getRequestedAt(): \DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeInterface $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return (string) $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getHandledByUserId(): ?int
    {
        return $this->handledByUserId;
    }

    public function setHandledByUserId(?int $handledByUserId): static
    {
        $this->handledByUserId = $handledByUserId;

        return $this;
    }

    public function getHandledAt(): ?\DateTimeInterface
    {
        return $this->handledAt;
    }

    public function setHandledAt(?\DateTimeInterface $handledAt): static
    {
        $this->handledAt = $handledAt;

        return $this;
    }
}