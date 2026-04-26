<?php

namespace App\Entity\UserManagement;

use App\Repository\UserManagement\UserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\Table(name: 'UserSession')]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'], inversedBy: 'userSessions')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'refresh_token_hash', length: 255)]
    private string $refresh_token_hash = '';

    #[ORM\Column(name: 'device_info', length: 255, nullable: true)]
    private ?string $device_info = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expires_at = null;

    #[ORM\Column(name: 'revoked', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $revoked = false;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRefreshTokenHash(): ?string
    {
        return $this->refresh_token_hash;
    }

    public function setRefreshTokenHash(string $refresh_token_hash): static
    {
        $this->refresh_token_hash = $refresh_token_hash;

        return $this;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->device_info;
    }

    public function setDeviceInfo(?string $device_info): static
    {
        $this->device_info = $device_info;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    protected function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpiresAt(\DateTimeInterface $expires_at): static
    {
        $this->expires_at = $expires_at;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): static
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expires_at < new \DateTime();
    }

    public function isValid(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
    }
}
