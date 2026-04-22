<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use App\Repository\UserManagement\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class IntrusionCaptureService
{
    private const FAILED_ATTEMPTS_THRESHOLD = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private string $appName = 'AgriLink',
    ) {}

    /**
     * Increment failed login attempts for a user
     */
    public function incrementFailedAttempts(User $user): void
    {
        $user->setFailedLoginAttempts($user->getFailedLoginAttempts() + 1);
        $this->entityManager->flush($user);
    }

    /**
     * Check if we should trigger intrusion capture on this failed attempt
     */
    public function shouldCaptureOnFailure(User $user): bool
    {
        // Only trigger if intrusion capture is enabled and we're on the 3rd attempt
        return $user->isIntrusionCaptureEnabled() && 
               $user->getFailedLoginAttempts() === (self::FAILED_ATTEMPTS_THRESHOLD - 1);
    }

    /**
     * Reset failed attempts on successful login
     */
    public function resetFailedAttempts(User $user): void
    {
        $user->setFailedLoginAttempts(0);
        $this->entityManager->flush($user);
    }

    /**
     * Send intrusion alert email with captured image
     * 
     * @param User $user The user whose account was targeted
     * @param string $base64Image Base64 encoded image data
     * @param string|null $userAgent User agent from the login attempt
     * @param string|null $ipAddress IP address from the login attempt
     */
    public function sendIntrusionAlert(
        User $user,
        string $base64Image,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): void {
        // Extract image data and MIME type from base64
        if (preg_match('/data:image\/(\w+);base64,(.+)/', $base64Image, $matches)) {
            $imageType = $matches[1];
            $imageData = base64_decode($matches[2], true);
        } else {
            // Try to decode as raw base64
            $imageData = base64_decode($base64Image, true);
            $imageType = 'jpeg';
        }

        if (!$imageData) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }

        // Create temporary file for the image
        $tempFile = tempnam(sys_get_temp_dir(), 'intrusion_');
        file_put_contents($tempFile, $imageData);

        try {
            // Generate CID that matches the template
            $attemptTime = new \DateTime();
            $imageCid = 'intrusion_' . $attemptTime->format('Y-m-d_H-i-s');

            $email = (new TemplatedEmail())
                ->from(new Address('security@agrilink.com', $this->appName))
                ->to($user->getEmail())
                ->subject('🔒 ' . $this->appName . ' - Alerte de sécurité : Tentative d\'intrusion')
                ->htmlTemplate('emails/intrusion_alert.html.twig')
                ->context([
                    'user' => $user,
                    'userAgent' => $userAgent,
                    'ipAddress' => $ipAddress,
                    'attemptTime' => $attemptTime,
                ])
                ->embed(
                    fopen($tempFile, 'r'),
                    $imageCid,
                    'image/' . $imageType
                );

            $this->mailer->send($email);
        } finally {
            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
