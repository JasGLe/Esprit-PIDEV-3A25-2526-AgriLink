<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailVerificationService
{
    private const CODE_LENGTH = 6;
    private const EXPIRATION_MINUTES = 15; // Changed from 24 hours to 15 minutes
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_RESEND_ATTEMPTS_PER_HOUR = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $mailerFrom = 'noreply@agrilink.com'
    ) {
    }

    /**
     * Generate a 6-digit verification code for the user
     */
    public function generateVerificationCode(User $user): string
    {
        // Generate 6-digit code (000000 to 999999)
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        
        // Set token and expiration (15 minutes)
        $user->setEmailVerificationToken($code);
        $user->setEmailVerificationExpiresAt(new \DateTime('+' . self::EXPIRATION_MINUTES . ' minutes'));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $code;
    }

    /**
     * Send verification email with the code
     */
    public function sendVerificationEmail(User $user): void
    {
        $code = $user->getEmailVerificationToken();
        
        if (!$code) {
            $code = $this->generateVerificationCode($user);
        }

        $htmlContent = $this->twig->render('emails/verification_code.html.twig', [
            'user' => $user,
            'code' => $code,
            'expirationMinutes' => self::EXPIRATION_MINUTES, // Changed to minutes
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('AgriLink - Vérification de votre adresse email')
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    /**
     * Verify the code provided by the user
     */
    public function verifyCode(User $user, string $code): bool
    {
        // Check if token exists
        if (!$user->getEmailVerificationToken()) {
            return false;
        }

        // Check if token is expired
        if ($this->isExpired($user)) {
            return false;
        }

        // Verify the code (case-insensitive comparison)
        if ($user->getEmailVerificationToken() !== $code) {
            return false;
        }

        // Mark email as verified
        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Check if the verification token is expired
     */
    public function isExpired(User $user): bool
    {
        $expiresAt = $user->getEmailVerificationExpiresAt();
        
        if (!$expiresAt) {
            return true;
        }

        return $expiresAt < new \DateTime();
    }

    /**
     * Check if user can resend verification email (rate limiting)
     */
    public function canResendVerification(User $user, array $resendAttempts = []): array
    {
        $now = new \DateTime();
        $oneHourAgo = (new \DateTime())->modify('-1 hour');
        
        // Filter attempts within the last hour
        $recentAttempts = array_filter($resendAttempts, function ($timestamp) use ($oneHourAgo) {
            return $timestamp > $oneHourAgo->getTimestamp();
        });

        // Check max attempts per hour
        if (count($recentAttempts) >= self::MAX_RESEND_ATTEMPTS_PER_HOUR) {
            $oldestAttempt = min($recentAttempts);
            $waitUntil = $oldestAttempt + 3600; // 1 hour after oldest attempt
            $waitSeconds = $waitUntil - time();
            
            return [
                'canResend' => false,
                'reason' => 'max_attempts',
                'waitSeconds' => max(0, $waitSeconds),
                'message' => sprintf('Vous avez atteint la limite de %d tentatives par heure. Réessayez dans %d minutes.', 
                    self::MAX_RESEND_ATTEMPTS_PER_HOUR, 
                    ceil($waitSeconds / 60)
                ),
            ];
        }

        // Check cooldown between resends
        if (!empty($recentAttempts)) {
            $lastAttempt = max($recentAttempts);
            $cooldownEnds = $lastAttempt + self::RESEND_COOLDOWN_SECONDS;
            
            if (time() < $cooldownEnds) {
                $waitSeconds = $cooldownEnds - time();
                
                return [
                    'canResend' => false,
                    'reason' => 'cooldown',
                    'waitSeconds' => $waitSeconds,
                    'message' => sprintf('Veuillez patienter %d secondes avant de renvoyer le code.', $waitSeconds),
                ];
            }
        }

        return [
            'canResend' => true,
            'reason' => null,
            'waitSeconds' => 0,
            'message' => null,
        ];
    }

    /**
     * Get time remaining until verification code expires (in seconds)
     */
    public function getExpirationTimeRemaining(User $user): int
    {
        $expiresAt = $user->getEmailVerificationExpiresAt();
        
        if (!$expiresAt) {
            return 0;
        }

        $remaining = $expiresAt->getTimestamp() - time();
        
        return max(0, $remaining);
    }

    /**
     * Regenerate and send a new verification code
     */
    public function regenerateAndSendCode(User $user): string
    {
        $code = $this->generateVerificationCode($user);
        $this->sendVerificationEmail($user);
        
        return $code;
    }
}
