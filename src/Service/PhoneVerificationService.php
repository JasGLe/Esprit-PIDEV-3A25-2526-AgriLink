<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class PhoneVerificationService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRATION_MINUTES = 10;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_OTP_ATTEMPTS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TwilioSmsService $twilioService,
        private Environment $twig,
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl = 'http://localhost'
    ) {
    }

    /**
     * Generate a 6-digit OTP code and store it in the user entity.
     * Sets expiration to 10 minutes from now.
     *
     * @param User $user The user to generate OTP for
     * @return string The generated OTP code
     */
    public function generateVerificationCode(User $user): string
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
     * Send the OTP code via SMS to the user's phone number.
     *
     * @param User $user The user to send SMS to
     * @return bool True if SMS sent successfully, false otherwise
     */
    public function sendVerificationSms(User $user): bool
    {
        $code = $user->getOtpCode();

        if ($code === null) {
            throw new \RuntimeException('Aucun code OTP à envoyer. Générez d\'abord un code.');
        }

        $phoneNumber = $user->getTelephone();
        if (empty($phoneNumber)) {
            return false;
        }

        return $this->twilioService->sendOtpSms($phoneNumber, $code);
    }

    /**
     * Verify the OTP code entered by the user.
     * Returns true if valid, false otherwise.
     * Handles attempt counting and invalidation after max attempts.
     *
     * @param User $user The user verifying the code
     * @param string $code The code to verify
     * @return bool True if code is valid, false otherwise
     */
    public function verifyCode(User $user, string $code): bool
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
            // Success - mark phone as verified and reset OTP
            $user->setPhoneVerified(true);
            $this->resetOtp($user);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
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
     *
     * @param User $user The user to check
     * @return bool True if can resend
     */
    public function canResendVerification(User $user): bool
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
     *
     * @param User $user The user to check
     * @return int Seconds remaining before can resend
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
     *
     * @param User $user The user to check
     * @return int Number of attempts remaining
     */
    public function getRemainingAttempts(User $user): int
    {
        return max(0, self::MAX_OTP_ATTEMPTS - $user->getOtpAttempts());
    }

    /**
     * Get OTP expiration time remaining in seconds.
     *
     * @param User $user The user to check
     * @return int Seconds remaining until expiration
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
     *
     * @param User $user The user to check
     * @return bool True if expired
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
     *
     * @param User $user The user to reset
     */
    public function resetOtp(User $user): void
    {
        $user->resetOtp();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Get max attempts constant.
     *
     * @return int Maximum attempts allowed
     */
    public function getMaxAttempts(): int
    {
        return self::MAX_OTP_ATTEMPTS;
    }

    /**
     * Get expiration time in minutes.
     *
     * @return int Expiration time in minutes
     */
    public function getExpirationMinutes(): int
    {
        return self::OTP_EXPIRATION_MINUTES;
    }

    /**
     * Get cooldown time in seconds.
     *
     * @return int Cooldown time in seconds
     */
    public function getCooldownSeconds(): int
    {
        return self::OTP_RESEND_COOLDOWN_SECONDS;
    }

    /**
     * Check if user has verified their phone number.
     *
     * @param User $user The user to check
     * @return bool True if phone is verified
     */
    public function isPhoneVerified(User $user): bool
    {
        return $user->isPhoneVerified();
    }

    /**
     * Check if phone number is set and valid format.
     *
     * @param User $user The user to check
     * @return bool True if phone number exists
     */
    public function hasPhoneNumber(User $user): bool
    {
        return !empty($user->getTelephone());
    }
}
