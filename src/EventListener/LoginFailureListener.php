<?php

namespace App\EventListener;

use App\Entity\UserManagement\User;
use App\Repository\UserManagement\UserRepository;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Twig\Environment;

class LoginFailureListener
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_DURATION_MINUTES = 15;
    private const SUSPICIOUS_THRESHOLD = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SecurityEventService $securityEventService,
        private MailerInterface $mailer,
        private Environment $twig
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        // Get email from the login form request
        $email = $request->request->get('_username');

        if (!$email) {
            $this->securityEventService->logLoginFailed(null, 'No email provided');
            return;
        }

        // Find user by email
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $this->securityEventService->logLoginFailed($email, 'User not found');
            return;
        }

        // Increment failed login attempts
        $user->incrementFailedLoginAttempts();

        // Lock account if max attempts reached
        $accountLocked = false;
        if ($user->getFailedLoginAttempts() >= self::MAX_FAILED_ATTEMPTS) {
            $lockedUntil = new \DateTime();
            $lockedUntil->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes');
            $user->setLockedUntil($lockedUntil);
            $accountLocked = true;
        }

        // Persist changes
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Log failed login attempt
        $this->securityEventService->logLoginFailed($email, 'Invalid credentials');

        // Log account locked if applicable
        if ($accountLocked) {
            $this->securityEventService->logAccountLocked($user);
        }

        // Send suspicious login alert email at 3+ failed attempts
        if ($user->getFailedLoginAttempts() >= self::SUSPICIOUS_THRESHOLD && $user->isEmailVerified()) {
            try {
                $htmlContent = $this->twig->render('emails/suspicious_login.html.twig', [
                    'user' => $user,
                    'ipAddress' => $request->getClientIp(),
                    'userAgent' => $request->headers->get('User-Agent'),
                    'timestamp' => new \DateTime(),
                    'attempts' => $user->getFailedLoginAttempts(),
                    'accountLocked' => $accountLocked,
                ]);

                $alertEmail = (new Email())
                    ->from('noreply@agrilink.com')
                    ->to($user->getEmail())
                    ->subject('AgriLink - Alerte de sécurité : tentatives de connexion suspectes')
                    ->html($htmlContent);

                $this->mailer->send($alertEmail);
            } catch (\Exception $e) {
                // Don't block the login flow if email fails
            }
        }
    }
}
