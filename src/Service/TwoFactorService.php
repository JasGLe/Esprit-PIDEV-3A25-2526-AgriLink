<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class TwoFactorService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRATION_MINUTES = 10;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_OTP_ATTEMPTS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig
    ) {
    }

    /**
     * Generate a 6-digit OTP code and store it in the user entity.
     * Sets expiration to 10 minutes from now.
     */
    public function generateOtp(User $user): string
    {
        // Generate 6-digit code (000000-999999)
        $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
        
        // Set OTP data on user
        $user->setOtpCode($code);
        $user->setOtpExpiration(new \DateTime('+' . self::OTP_EXPIRATION_MINUTES . ' minutes'));
        $user->setOtpAttempts(0);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $code;
    }

    /**
     * Send the OTP code via email to the user.
     */
    public function sendOtpEmail(User $user): void
    {
        $code = $user->getOtpCode();
        
        if ($code === null) {
            throw new \RuntimeException('Aucun code OTP à envoyer. Générez d\'abord un code.');
        }

        $htmlContent = $this->twig->render('emails/2fa_code.html.twig', [
            'user' => $user,
            'code' => $code,
            'expiration_minutes' => self::OTP_EXPIRATION_MINUTES,
        ]);

        $email = (new Email())
            ->from('noreply@agrilink.com')
            ->to($user->getEmail())
            ->subject('Votre code de vérification AgriLink')
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    /**
     * Verify the OTP code entered by the user.
     * Returns true if valid, false otherwise.
     * Handles attempt counting and invalidation after max attempts.
     */
    public function verifyOtp(User $user, string $code): bool
    {
        // Check if OTP exists
        if ($user->getOtpCode() === null) {
            return false;
        }

        // Check if max attempts reached
        if ($user->getOtpAttempts() >= self::MAX_OTP_ATTEMPTS) {
            $this->resetOtp($user);
            return false;
        }

        // Verify the code
        if ($user->isOtpValid($code)) {
            // Success - reset OTP
            $this->resetOtp($user);
            return true;
        }

        // Failed attempt - increment counter
        $user->incrementOtpAttempts();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // If max attempts reached after this attempt, reset OTP
        if ($user->getOtpAttempts() >= self::MAX_OTP_ATTEMPTS) {
            $this->resetOtp($user);
        }

        return false;
    }

    /**
     * Check if enough time has passed to resend OTP (60-second cooldown).
     */
    public function canResendOtp(User $user): bool
    {
        $expiration = $user->getOtpExpiration();
        
        if ($expiration === null) {
            return true; // No existing OTP, can send new one
        }

        // Calculate when the OTP was created (expiration - 10 minutes)
        $createdAt = (clone $expiration)->modify('-' . self::OTP_EXPIRATION_MINUTES . ' minutes');
        $cooldownEnd = (clone $createdAt)->modify('+' . self::OTP_RESEND_COOLDOWN_SECONDS . ' seconds');
        
        return new \DateTime() >= $cooldownEnd;
    }

    /**
     * Get remaining cooldown time in seconds.
     */
    public function getRemainingCooldown(User $user): int
    {
        $expiration = $user->getOtpExpiration();
        
        if ($expiration === null) {
            return 0;
        }

        $createdAt = (clone $expiration)->modify('-' . self::OTP_EXPIRATION_MINUTES . ' minutes');
        $cooldownEnd = (clone $createdAt)->modify('+' . self::OTP_RESEND_COOLDOWN_SECONDS . ' seconds');
        $now = new \DateTime();
        
        if ($now >= $cooldownEnd) {
            return 0;
        }
        
        return $cooldownEnd->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Get remaining attempts before OTP is invalidated.
     */
    public function getRemainingAttempts(User $user): int
    {
        return max(0, self::MAX_OTP_ATTEMPTS - $user->getOtpAttempts());
    }

    /**
     * Get OTP expiration time remaining in seconds.
     */
    public function getOtpExpirationSeconds(User $user): int
    {
        $expiration = $user->getOtpExpiration();
        
        if ($expiration === null) {
            return 0;
        }

        $now = new \DateTime();
        
        if ($now >= $expiration) {
            return 0;
        }
        
        return $expiration->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Check if OTP has expired.
     */
    public function isOtpExpired(User $user): bool
    {
        $expiration = $user->getOtpExpiration();
        
        if ($expiration === null) {
            return true;
        }
        
        return new \DateTime() >= $expiration;
    }

    /**
     * Reset all OTP data for the user.
     */
    public function resetOtp(User $user): void
    {
        $user->resetOtp();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Get max attempts constant.
     */
    public function getMaxAttempts(): int
    {
        return self::MAX_OTP_ATTEMPTS;
    }

    /**
     * Get expiration time in minutes.
     */
    public function getExpirationMinutes(): int
    {
        return self::OTP_EXPIRATION_MINUTES;
    }

    /**
     * Get cooldown time in seconds.
     */
    public function getCooldownSeconds(): int
    {
        return self::OTP_RESEND_COOLDOWN_SECONDS;
    }
}
