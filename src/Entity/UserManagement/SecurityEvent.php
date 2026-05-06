<?php

namespace App\Entity\UserManagement;

use App\Repository\UserManagement\SecurityEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityEventRepository::class)]
#[ORM\Table(name: 'security_event')]
#[ORM\HasLifecycleCallbacks]
class SecurityEvent
{
    public const EVENT_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const EVENT_LOGIN_FAILED = 'LOGIN_FAILED';
    public const EVENT_ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    public const EVENT_2FA_OTP_SENT = '2FA_OTP_SENT';
    public const EVENT_2FA_SUCCESS = '2FA_SUCCESS';
    public const EVENT_2FA_FAILED = '2FA_FAILED';
    public const EVENT_PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const EVENT_EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const EVENT_OAUTH_LOGIN = 'OAUTH_LOGIN';
    public const EVENT_OAUTH_REGISTER = 'OAUTH_REGISTER';
    public const EVENT_SESSION_REVOKED = 'SESSION_REVOKED';
    public const EVENT_LOGOUT = 'LOGOUT';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'SUSPICIOUS_ACTIVITY';
    public const EVENT_ACCOUNT_BANNED = 'ACCOUNT_BANNED';
    public const EVENT_ACCOUNT_UNBANNED = 'ACCOUNT_UNBANNED';
    public const EVENT_BACKUP_CODE_USED = 'BACKUP_CODE_USED';
    public const EVENT_EMAIL_CHANGE_REQUESTED = 'EMAIL_CHANGE_REQUESTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id; // @phpstan-ignore-line

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'securityEvents')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'event_type', type: Types::STRING, length: 50)]
    private string $eventType = '';

    #[ORM\Column(name: 'details', type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
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

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    protected function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    protected function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    protected function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    protected function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();

        if ($this->createdBy === null) {
            $this->createdBy = null;
        }
        if ($this->updatedBy === null) {
            $this->updatedBy = null;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
