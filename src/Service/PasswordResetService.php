<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

class PasswordResetService
{
    private const TOKEN_TTL = 3600; // 1 hour in seconds
    private const CACHE_PREFIX = 'password_reset_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        #[Autowire('%env(MAILER_FROM_EMAIL)%')]
        private readonly string $senderEmail,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $senderName
    ) {
    }

    /**
     * Generate a secure reset token for the user.
     * Token is 32 bytes, hex-encoded (64 chars).
     * SHA-256 hash of token is stored in cache.
     */
    public function generateResetToken(User $user): string
    {
        // Generate 32 random bytes, hex-encode to get 64 char token
        $token = bin2hex(random_bytes(32));
        
        // Hash the token for storage (store hash, not plain token)
        $tokenHash = hash('sha256', $token);
        
        // Store user ID in cache with hashed token as key
        $cacheKey = self::CACHE_PREFIX . $tokenHash;
        
        $this->cache->delete($cacheKey);
        
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(self::TOKEN_TTL);
            return $user->getId();
        });

        return $token;
    }

    /**
     * Send password reset email to user.
     */
    public function sendResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $htmlContent = $this->twig->render('emails/password_reset.html.twig', [
            'user' => $user,
            'reset_url' => $resetUrl,
        ]);

        $email = (new Email())
            ->from($this->senderName . ' <' . $this->senderEmail . '>')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - AgriLink')
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    /**
     * Validate token and return user if valid.
     * Returns null if token is invalid or expired.
     */
    public function validateToken(string $token): ?User
    {
        // Hash the provided token
        $tokenHash = hash('sha256', $token);
        $cacheKey = self::CACHE_PREFIX . $tokenHash;

        try {
            // Try to get the user ID from cache
            $userId = $this->cache->get($cacheKey, function (ItemInterface $item) {
                // If we reach here, the item doesn't exist
                $item->expiresAfter(0); // Don't cache this
                return null;
            });

            if ($userId === null) {
                return null;
            }

            return $this->userRepository->find($userId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Reset user password, invalidate token, and revoke all sessions.
     */
    public function resetPassword(User $user, string $newPassword, string $token): void
    {
        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        // Reset failed login attempts if any
        if (method_exists($user, 'setFailedLoginAttempts')) {
            $user->setFailedLoginAttempts(0);
        }
        
        // Clear any lock
        if (method_exists($user, 'setLockedUntil')) {
            $user->setLockedUntil(null);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Invalidate the token
        $tokenHash = hash('sha256', $token);
        $cacheKey = self::CACHE_PREFIX . $tokenHash;
        $this->cache->delete($cacheKey);
    }

    /**
     * Request password reset for an email address.
     * Returns true always (don't reveal if email exists).
     */
    public function requestPasswordReset(string $email): bool
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        if ($user !== null && $user->isActive()) {
            $token = $this->generateResetToken($user);
            $this->sendResetEmail($user, $token);
        }

        // Always return true - don't reveal if email exists
        return true;
    }
}
