<?php

namespace App\Entity\UserManagement;

use App\Entity\Exploitation\Exploitation;
use App\Repository\UserManagement\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;use Symfony\Component\Serializer\Annotation\Ignore;
use SensitiveParameter;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Role constants (match DB enum values)
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_AGRICULTEUR = 'AGRICULTEUR';
    public const ROLE_AGRIPLUS = 'AGRIPLUS';
    public const ROLE_USER = 'USER';
    public const ROLE_FOURNISSEUR = 'FOURNISSEUR';

    // Admin level constants
    public const ADMIN_SUPER = 'SUPER_ADMIN';
    public const ADMIN_NORMAL = 'ADMIN';
    public const ADMIN_MODERATOR = 'MODERATOR';

    // AgriPlus subscription constants
    public const SUBSCRIPTION_BASIC = 'BASIC';
    public const SUBSCRIPTION_PREMIUM = 'PREMIUM';
    public const SUBSCRIPTION_ENTERPRISE = 'ENTERPRISE';

    // Fournisseur type constants
    public const FOURNISSEUR_PERSONNE = 'PERSONNE';
    public const FOURNISSEUR_SOCIETE = 'SOCIETE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'Nom', length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private string $nom = '';

    #[ORM\Column(name: 'DateNaissance', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(name: 'Email', length: 150, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    #[Assert\Length(max: 150, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères.")]
    private string $email = '';

    #[ORM\Column(name: 'telephone', length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[0-9\s\+\-\(\)]+$/',
        message: "Le numéro de téléphone n'est pas valide."
    )]
    private ?string $telephone = null;

    #[ORM\Column(name: 'Mdp', length: 255)]
    #[Ignore]
    private string $password = '';

    #[ORM\Column(name: 'PhotoProfil', length: 255, nullable: true)]
    private ?string $photoProfil = null;

    #[ORM\Column(name: 'Gouvernant', length: 100, nullable: true)]
    private ?string $gouvernant = null;

    #[ORM\Column(name: 'Ville', length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'CodePostale', length: 10, nullable: true)]
    private ?string $codePostale = null;

    #[ORM\Column(name: 'role', type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Le rôle est obligatoire.')]
    private string $role = self::ROLE_USER;

    #[ORM\Column(name: 'admin_niveau', type: Types::STRING, length: 20, nullable: true)]
    private ?string $adminNiveau = null;

    #[ORM\Column(name: 'agriculteur_expAnnee', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: "L'expérience doit être positive.")]
    private ?int $agriculteurExpAnnee = null;

    #[ORM\Column(name: 'agriplus_abonnement', type: Types::STRING, length: 20, nullable: true)]
    private ?string $agriplusAbonnement = null;

    #[ORM\Column(name: 'agriplus_dateExpiration', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $agriplusDateExpiration = null;

    #[ORM\Column(name: 'fournisseur_typeFournisseur', type: Types::STRING, length: 20, nullable: true)]
    private ?string $fournisseurTypeFournisseur = null;

    #[ORM\Column(name: 'fournisseur_certifications', type: Types::TEXT, nullable: true)]
    private ?string $fournisseurCertifications = null;

    #[ORM\Column(name: 'fournisseur_cin', length: 20, nullable: true)]
    private ?string $fournisseurCin = null;

    #[ORM\Column(name: 'fournisseur_raisonSocial', length: 200, nullable: true)]
    private ?string $fournisseurRaisonSocial = null;

    #[ORM\Column(name: 'fournisseur_numRegistre', length: 50, nullable: true)]
    private ?string $fournisseurNumRegistre = null;

    #[ORM\Column(name: 'fournisseur_formeJuridique', length: 100, nullable: true)]
    private ?string $fournisseurFormeJuridique = null;

    #[ORM\Column(name: 'fournisseur_capital', type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $fournisseurCapital = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'email_verified', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $emailVerified = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'oauth_provider', length: 20, nullable: true)]
    private ?string $oauthProvider = null;

    #[ORM\Column(name: 'oauth_provider_id', length: 255, nullable: true)]
    private ?string $oauthProviderId = null;

    #[ORM\Column(name: 'password_migrated', type: Types::BOOLEAN, options: ['default' => false])]
    #[Ignore]
    private bool $passwordMigrated = false;

    #[ORM\Column(name: 'failed_login_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(name: 'locked_until', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lockedUntil = null;

    #[ORM\Column(name: 'last_login', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(name: 'email_verification_token', length: 10, nullable: true)]
    #[Ignore]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(name: 'email_verification_expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $emailVerificationExpiresAt = null;

    #[ORM\Column(name: 'two_factor_enabled', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(name: 'intrusion_capture_enabled', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $intrusionCaptureEnabled = false;

    #[ORM\Column(name: 'phone_verified', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $phoneVerified = false;

    #[ORM\Column(name: 'otp_code', length: 10, nullable: true)]
    private ?string $otpCode = null;

    #[ORM\Column(name: 'otp_expiration', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $otpExpiration = null;

    #[ORM\Column(name: 'otp_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $otpAttempts = 0;

    #[ORM\Column(name: 'face_descriptor', type: Types::TEXT, nullable: true)]
    private ?string $faceDescriptor = null;

    #[ORM\Column(name: 'face_enrolled_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $faceEnrolledAt = null;

    #[ORM\Column(name: 'voice_embedding', type: Types::TEXT, nullable: true)]
    private ?string $voiceEmbedding = null;

    #[ORM\Column(name: 'voice_enrolled_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $voiceEnrolledAt = null;

    #[ORM\Column(name: 'voice_enrollment_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $voiceEnrollmentAttempts = 0;

    #[ORM\Column(name: 'pending_email', length: 150, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(name: 'is_banned', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isBanned = false;

    #[ORM\Column(name: 'banned_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;

    #[ORM\Column(name: 'ban_reason', length: 500, nullable: true)]
    private ?string $banReason = null;

    #[ORM\Column(name: 'ban_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $banCount = 0;

    #[ORM\Column(name: 'banned_until', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedUntil = null;

    #[ORM\Column(name: 'is_permanently_banned', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPermanentlyBanned = false;

    #[ORM\Column(name: 'backup_codes', type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(name: 'login_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $loginCount = 0;

    #[ORM\Column(name: 'user_points', type: Types::INTEGER, options: ['default' => 0])]
    private int $userPoints = 0;

    #[ORM\Column(name: 'earned_badges', type: Types::JSON, nullable: true)]
    private ?array $earnedBadges = null;

    #[ORM\OneToMany(targetEntity: Exploitation::class, mappedBy: 'user')]
    private Collection $exploitations;

    #[ORM\OneToMany(targetEntity: SecurityEvent::class, mappedBy: 'user')]
    private Collection $securityEvents;

    #[ORM\OneToMany(targetEntity: UserSession::class, mappedBy: 'user')]
    private Collection $userSessions;

    public function __construct()
    {
        $this->exploitations = new ArrayCollection();
        $this->securityEvents = new ArrayCollection();
        $this->userSessions = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
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

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(#[SensitiveParameter] string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPhotoProfil(): ?string
    {
        return $this->photoProfil;
    }

    public function setPhotoProfil(?string $photoProfil): static
    {
        $this->photoProfil = $photoProfil;
        return $this;
    }

    public function getGouvernant(): ?string
    {
        return $this->gouvernant;
    }

    public function setGouvernant(?string $gouvernant): static
    {
        $this->gouvernant = $gouvernant;
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

    public function getCodePostale(): ?string
    {
        return $this->codePostale;
    }

    public function setCodePostale(?string $codePostale): static
    {
        $this->codePostale = $codePostale;
        return $this;
    }

    /**
     * Get the raw role value from database (single string)
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Set the raw role value (single string matching DB enum)
     */
    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @see UserInterface
     * Returns Symfony-compatible roles array based on the single DB role
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        switch ($this->role) {
            case self::ROLE_ADMIN:
                $roles[] = 'ROLE_ADMIN';
                if ($this->adminNiveau === self::ADMIN_SUPER) {
                    $roles[] = 'ROLE_SUPER_ADMIN';
                } elseif ($this->adminNiveau === self::ADMIN_MODERATOR) {
                    $roles[] = 'ROLE_MODERATOR';
                }
                break;
            case self::ROLE_AGRICULTEUR:
                $roles[] = 'ROLE_AGRICULTEUR';
                break;
            case self::ROLE_AGRIPLUS:
                $roles[] = 'ROLE_AGRIPLUS';
                $roles[] = 'ROLE_AGRICULTEUR';
                break;
            case self::ROLE_FOURNISSEUR:
                $roles[] = 'ROLE_FOURNISSEUR';
                if ($this->fournisseurTypeFournisseur === self::FOURNISSEUR_PERSONNE) {
                    $roles[] = 'ROLE_FOURNISSEUR_PERSONNE';
                } elseif ($this->fournisseurTypeFournisseur === self::FOURNISSEUR_SOCIETE) {
                    $roles[] = 'ROLE_FOURNISSEUR_SOCIETE';
                }
                break;
        }

        return array_unique($roles);
    }

    public function getAdminNiveau(): ?string
    {
        return $this->adminNiveau;
    }

    public function setAdminNiveau(?string $adminNiveau): static
    {
        $this->adminNiveau = $adminNiveau;
        return $this;
    }

    public function getAgriculteurExpAnnee(): ?int
    {
        return $this->agriculteurExpAnnee;
    }

    public function setAgriculteurExpAnnee(?int $agriculteurExpAnnee): static
    {
        $this->agriculteurExpAnnee = $agriculteurExpAnnee;
        return $this;
    }

    public function getAgriplusAbonnement(): ?string
    {
        return $this->agriplusAbonnement;
    }

    public function setAgriplusAbonnement(?string $agriplusAbonnement): static
    {
        $this->agriplusAbonnement = $agriplusAbonnement;
        return $this;
    }

    public function getAgriplusDateExpiration(): ?\DateTimeInterface
    {
        return $this->agriplusDateExpiration;
    }

    public function setAgriplusDateExpiration(?\DateTimeInterface $agriplusDateExpiration): static
    {
        $this->agriplusDateExpiration = $agriplusDateExpiration;
        return $this;
    }

    public function getFournisseurTypeFournisseur(): ?string
    {
        return $this->fournisseurTypeFournisseur;
    }

    public function setFournisseurTypeFournisseur(?string $fournisseurTypeFournisseur): static
    {
        $this->fournisseurTypeFournisseur = $fournisseurTypeFournisseur;
        return $this;
    }

    public function getFournisseurCertifications(): ?string
    {
        return $this->fournisseurCertifications;
    }

    public function setFournisseurCertifications(?string $fournisseurCertifications): static
    {
        $this->fournisseurCertifications = $fournisseurCertifications;
        return $this;
    }

    public function getFournisseurCin(): ?string
    {
        return $this->fournisseurCin;
    }

    public function setFournisseurCin(?string $fournisseurCin): static
    {
        $this->fournisseurCin = $fournisseurCin;
        return $this;
    }

    public function getFournisseurRaisonSocial(): ?string
    {
        return $this->fournisseurRaisonSocial;
    }

    public function setFournisseurRaisonSocial(?string $fournisseurRaisonSocial): static
    {
        $this->fournisseurRaisonSocial = $fournisseurRaisonSocial;
        return $this;
    }

    public function getFournisseurNumRegistre(): ?string
    {
        return $this->fournisseurNumRegistre;
    }

    public function setFournisseurNumRegistre(?string $fournisseurNumRegistre): static
    {
        $this->fournisseurNumRegistre = $fournisseurNumRegistre;
        return $this;
    }

    public function getFournisseurFormeJuridique(): ?string
    {
        return $this->fournisseurFormeJuridique;
    }

    public function setFournisseurFormeJuridique(?string $fournisseurFormeJuridique): static
    {
        $this->fournisseurFormeJuridique = $fournisseurFormeJuridique;
        return $this;
    }

    public function getFournisseurCapital(): ?string
    {
        return $this->fournisseurCapital;
    }

    public function setFournisseurCapital(?string $fournisseurCapital): static
    {
        $this->fournisseurCapital = $fournisseurCapital;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;
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

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    protected function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getOauthProvider(): ?string
    {
        return $this->oauthProvider;
    }

    public function setOauthProvider(?string $oauthProvider): static
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    public function getOauthProviderId(): ?string
    {
        return $this->oauthProviderId;
    }

    public function setOauthProviderId(?string $oauthProviderId): static
    {
        $this->oauthProviderId = $oauthProviderId;
        return $this;
    }

    public function isPasswordMigrated(): bool
    {
        return $this->passwordMigrated;
    }

    public function setPasswordMigrated(#[SensitiveParameter] bool $passwordMigrated): self
    {
        $this->passwordMigrated = $passwordMigrated;
        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): static
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
        return $this;
    }

    public function getLockedUntil(): ?\DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeInterface $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(#[SensitiveParameter] ?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?\DateTimeInterface $emailVerificationExpiresAt): static
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function isIntrusionCaptureEnabled(): bool
    {
        return $this->intrusionCaptureEnabled;
    }

    public function setIntrusionCaptureEnabled(bool $intrusionCaptureEnabled): static
    {
        $this->intrusionCaptureEnabled = $intrusionCaptureEnabled;
        return $this;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    public function setPhoneVerified(bool $phoneVerified): static
    {
        $this->phoneVerified = $phoneVerified;
        return $this;
    }

    public function getOtpCode(): ?string
    {
        return $this->otpCode;
    }

    public function setOtpCode(?string $otpCode): static
    {
        $this->otpCode = $otpCode;
        return $this;
    }

    public function getOtpExpiration(): ?\DateTimeInterface
    {
        return $this->otpExpiration;
    }

    public function setOtpExpiration(?\DateTimeInterface $otpExpiration): static
    {
        $this->otpExpiration = $otpExpiration;
        return $this;
    }

    public function getOtpAttempts(): int
    {
        return $this->otpAttempts;
    }

    public function setOtpAttempts(int $otpAttempts): static
    {
        $this->otpAttempts = $otpAttempts;
        return $this;
    }

    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    public function getFaceEnrolledAt(): ?\DateTimeInterface
    {
        return $this->faceEnrolledAt;
    }

    public function setFaceEnrolledAt(?\DateTimeInterface $faceEnrolledAt): static
    {
        $this->faceEnrolledAt = $faceEnrolledAt;
        return $this;
    }

    public function getVoiceEmbedding(): ?string
    {
        return $this->voiceEmbedding;
    }

    public function setVoiceEmbedding(?string $voiceEmbedding): static
    {
        $this->voiceEmbedding = $voiceEmbedding;
        return $this;
    }

    public function getVoiceEnrolledAt(): ?\DateTimeInterface
    {
        return $this->voiceEnrolledAt;
    }

    public function setVoiceEnrolledAt(?\DateTimeInterface $voiceEnrolledAt): static
    {
        $this->voiceEnrolledAt = $voiceEnrolledAt;
        return $this;
    }

    public function getVoiceEnrollmentAttempts(): int
    {
        return $this->voiceEnrollmentAttempts;
    }

    public function setVoiceEnrollmentAttempts(int $voiceEnrollmentAttempts): static
    {
        $this->voiceEnrollmentAttempts = $voiceEnrollmentAttempts;
        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): static
    {
        $this->pendingEmail = $pendingEmail;
        return $this;
    }

    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function getBannedAt(): ?\DateTimeInterface
    {
        return $this->bannedAt;
    }

    public function getBanReason(): ?string
    {
        return $this->banReason;
    }

    public function ban(string $reason): static
    {
        $this->isBanned = true;
        $this->bannedAt = new \DateTime();
        $this->banReason = $reason;
        return $this;
    }

    public function unban(): static
    {
        $this->isBanned = false;
        $this->bannedAt = null;
        $this->banReason = null;
        $this->bannedUntil = null;
        $this->isPermanentlyBanned = false;
        return $this;
    }

    public function getBanCount(): int
    {
        return $this->banCount;
    }

    public function setBanCount(int $banCount): static
    {
        $this->banCount = max(0, $banCount);

        return $this;
    }

    public function getBannedUntil(): ?\DateTimeInterface
    {
        return $this->bannedUntil;
    }

    public function setBannedUntil(?\DateTimeInterface $bannedUntil): static
    {
        $this->bannedUntil = $bannedUntil;

        return $this;
    }

    public function isPermanentlyBanned(): bool
    {
        return $this->isPermanentlyBanned;
    }

    public function setIsPermanentlyBanned(bool $isPermanentlyBanned): static
    {
        $this->isPermanentlyBanned = $isPermanentlyBanned;

        return $this;
    }

    public function isCurrentlyBanned(): bool
    {
        if ($this->isPermanentlyBanned) {
            return true;
        }

        if ($this->bannedUntil !== null) {
            return $this->bannedUntil > new \DateTimeImmutable();
        }

        // Legacy/manual ban flag without timed ban metadata.
        return $this->isBanned;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): static
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    // ==================== USERINTERFACE METHODS ====================

    /**
     * A visual identifier that represents this user.
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Clear any temporary sensitive data if stored
    }

    // ==================== LIFECYCLE CALLBACKS ====================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is an agriculteur
     */
    public function isAgriculteur(): bool
    {
        return $this->role === self::ROLE_AGRICULTEUR;
    }

    /**
     * Check if user is AgriPlus (premium agriculteur)
     */
    public function isAgriPlus(): bool
    {
        return $this->role === self::ROLE_AGRIPLUS;
    }

    /**
     * Check if user is a fournisseur (any type)
     */
    public function isFournisseur(): bool
    {
        return $this->role === self::ROLE_FOURNISSEUR;
    }

    /**
     * Check if user is a fournisseur personne physique
     */
    public function isFournisseurPersonne(): bool
    {
        return $this->isFournisseur() && $this->fournisseurTypeFournisseur === self::FOURNISSEUR_PERSONNE;
    }

    /**
     * Check if user is a fournisseur société
     */
    public function isFournisseurSociete(): bool
    {
        return $this->isFournisseur() && $this->fournisseurTypeFournisseur === self::FOURNISSEUR_SOCIETE;
    }

    /**
     * Check if account is currently locked out
     */
    public function isLockedOut(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }
        return $this->lockedUntil > new \DateTime();
    }

    /**
     * Get display name (nom or email)
     */
    public function getDisplayName(): string
    {
        return $this->nom ?: $this->email ?? 'Utilisateur';
    }

    /**
     * Get role badge in French
     */
    public function getRoleBadge(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => match ($this->adminNiveau) {
                self::ADMIN_SUPER => 'Super Admin',
                self::ADMIN_MODERATOR => 'Modérateur',
                default => 'Administrateur',
            },
            self::ROLE_AGRIPLUS => 'AgriPlus',
            self::ROLE_FOURNISSEUR => match ($this->fournisseurTypeFournisseur) {
                self::FOURNISSEUR_SOCIETE => 'Fournisseur Société',
                self::FOURNISSEUR_PERSONNE => 'Fournisseur Personne',
                default => 'Fournisseur',
            },
            self::ROLE_AGRICULTEUR => 'Agriculteur',
            default => 'Utilisateur',
        };
    }

    /**
     * Get initials for avatar fallback
     */
    public function getInitials(): string
    {
        if (!$this->nom) {
            return strtoupper(substr($this->email ?? 'U', 0, 2));
        }

        $parts = explode(' ', trim($this->nom));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }

        return strtoupper(substr($parts[0], 0, 2));
    }

    /**
     * Check if AgriPlus subscription is still valid
     */
    public function hasValidAgriplusSubscription(): bool
    {
        if ($this->role !== self::ROLE_AGRIPLUS) {
            return false;
        }
        if ($this->agriplusDateExpiration === null) {
            return false;
        }
        return $this->agriplusDateExpiration >= new \DateTime('today');
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts++;
        return $this;
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
        return $this;
    }

    /**
     * Lock account for a duration
     */
    public function lockAccount(\DateInterval $duration): static
    {
        $this->lockedUntil = (new \DateTime())->add($duration);
        return $this;
    }

    /**
     * Increment OTP attempts
     */
    public function incrementOtpAttempts(): static
    {
        $this->otpAttempts++;
        return $this;
    }

    /**
     * Reset OTP
     */
    public function resetOtp(): static
    {
        $this->otpCode = null;
        $this->otpExpiration = null;
        $this->otpAttempts = 0;
        return $this;
    }

    /**
     * Check if OTP is valid
     */
    public function isOtpValid(string $code): bool
    {
        if ($this->otpCode === null || $this->otpExpiration === null) {
            return false;
        }
        if ($this->otpExpiration < new \DateTime()) {
            return false;
        }
        return $this->otpCode === $code;
    }

    // ==================== COLLECTION GETTERS ====================

    /**
     * @return Collection<int, Exploitation>
     */
    public function getExploitations(): Collection
    {
        return $this->exploitations;
    }

    public function addExploitation(Exploitation $exploitation): static
    {
        if (!$this->exploitations->contains($exploitation)) {
            $this->exploitations->add($exploitation);
            $exploitation->setUser($this);
        }

        return $this;
    }

    public function removeExploitation(Exploitation $exploitation): static
    {
        if ($this->exploitations->removeElement($exploitation)) {
            if ($exploitation->getUser() === $this) {
                $exploitation->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SecurityEvent>
     */
    public function getSecurityEvents(): Collection
    {
        return $this->securityEvents;
    }

    public function addSecurityEvent(SecurityEvent $securityEvent): static
    {
        if (!$this->securityEvents->contains($securityEvent)) {
            $this->securityEvents->add($securityEvent);
            $securityEvent->setUser($this);
        }

        return $this;
    }

    public function removeSecurityEvent(SecurityEvent $securityEvent): static
    {
        if ($this->securityEvents->removeElement($securityEvent)) {
            if ($securityEvent->getUser() === $this) {
                $securityEvent->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function getUserSessions(): Collection
    {
        return $this->userSessions;
    }

    public function addUserSession(UserSession $userSession): static
    {
        if (!$this->userSessions->contains($userSession)) {
            $this->userSessions->add($userSession);
            $userSession->setUser($this);
        }

        return $this;
    }

    public function removeUserSession(UserSession $userSession): static
    {
        if ($this->userSessions->removeElement($userSession)) {
            if ($userSession->getUser() === $this) {
                $userSession->setUser(null);
            }
        }

        return $this;
    }

    // ==================== GAMIFICATION ====================

    public function getLoginCount(): int
    {
        return $this->loginCount;
    }

    public function setLoginCount(int $loginCount): static
    {
        $this->loginCount = $loginCount;
        return $this;
    }

    public function incrementLoginCount(): static
    {
        $this->loginCount++;
        return $this;
    }

    public function getUserPoints(): int
    {
        return $this->userPoints;
    }

    public function setUserPoints(int $userPoints): static
    {
        $this->userPoints = $userPoints;
        return $this;
    }

    public function getEarnedBadges(): array
    {
        return $this->earnedBadges ?? [];
    }

    public function setEarnedBadges(?array $earnedBadges): static
    {
        $this->earnedBadges = $earnedBadges;
        return $this;
    }
}
