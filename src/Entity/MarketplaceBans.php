<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\MarketplaceBansRepository::class)]
#[ORM\Table(name: 'marketplace_bans')]
class MarketplaceBans
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $reason;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $bannedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $liftedAt = null;

    #[ORM\Column(type: Types::STRING, length: 1000, nullable: true)]
    private ?string $appealText = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $appealAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getBannedAt(): \DateTimeInterface
    {
        return $this->bannedAt;
    }

    public function setBannedAt(\DateTimeInterface $bannedAt): static
    {
        $this->bannedAt = $bannedAt;

        return $this;
    }

    public function getLiftedAt(): ?\DateTimeInterface
    {
        return $this->liftedAt;
    }

    public function setLiftedAt(?\DateTimeInterface $liftedAt): static
    {
        $this->liftedAt = $liftedAt;

        return $this;
    }

    public function getAppealText(): ?string
    {
        return $this->appealText;
    }

    public function setAppealText(?string $appealText): static
    {
        $this->appealText = $appealText;

        return $this;
    }

    public function getAppealAt(): ?\DateTimeInterface
    {
        return $this->appealAt;
    }

    public function setAppealAt(?\DateTimeInterface $appealAt): static
    {
        $this->appealAt = $appealAt;

        return $this;
    }
}