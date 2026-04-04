<?php

namespace App\Controller\UserManagement;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EmailVerificationController extends AbstractController
{
    private const SESSION_RESEND_ATTEMPTS_KEY = 'email_verification_resend_attempts';

    public function __construct(
        private EmailVerificationService $emailVerificationService
    ) {
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function verifyEmail(Request $request): Response
    {
        $user = $this->getUser();

        // If already verified, redirect to dashboard
        if ($user->isEmailVerified()) {
            $this->addFlash('info', 'Votre adresse email est déjà vérifiée.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Track referer so we can redirect back to security settings after verification
        if ($request->query->get('from') === 'profile') {
            $request->getSession()->set('_verify_email_referer', 'profile');
        }

        // If no verification token exists, generate one
        if (!$user->getEmailVerificationToken()) {
            $this->emailVerificationService->generateVerificationCode($user);
            $this->emailVerificationService->sendVerificationEmail($user);
        }

        // Handle form submission
        if ($request->isMethod('POST')) {
            $code = $request->request->get('verification_code', '');
            $code = trim($code);

            if (empty($code)) {
                $this->addFlash('error', 'Veuillez entrer le code de vérification.');
                return $this->redirectToRoute('app_verify_email');
            }

            if (strlen($code) !== 6 || !ctype_digit($code)) {
                $this->addFlash('error', 'Le code de vérification doit contenir 6 chiffres.');
                return $this->redirectToRoute('app_verify_email');
            }

            // Check if expired
            if ($this->emailVerificationService->isExpired($user)) {
                $this->addFlash('error', 'Le code de vérification a expiré. Veuillez demander un nouveau code.');
                return $this->redirectToRoute('app_verify_email');
            }

            // Verify the code
            if ($this->emailVerificationService->verifyCode($user, $code)) {
                $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès !');

                // Clear resend attempts from session
                $request->getSession()->remove(self::SESSION_RESEND_ATTEMPTS_KEY);

                // Redirect to security settings if coming from profile, else dashboard
                $referer = $request->getSession()->get('_verify_email_referer');
                $request->getSession()->remove('_verify_email_referer');

                return $this->redirectToRoute($referer === 'profile' ? 'app_profile_security' : 'app_dashboard');
            } else {
                $this->addFlash('error', 'Code de vérification incorrect. Veuillez réessayer.');
                return $this->redirectToRoute('app_verify_email');
            }
        }

        // Calculate time remaining
        $expiresAt = $user->getEmailVerificationExpiresAt();
        $timeRemaining = $this->emailVerificationService->getExpirationTimeRemaining($user);

        // Get resend attempts for rate limiting display
        $resendAttempts = $request->getSession()->get(self::SESSION_RESEND_ATTEMPTS_KEY, []);
        $canResendInfo = $this->emailVerificationService->canResendVerification($user, $resendAttempts);

        return $this->render('user_management/security/verify_email.html.twig', [
            'user' => $user,
            'expiresAt' => $expiresAt,
            'timeRemaining' => $timeRemaining,
            'canResend' => $canResendInfo['canResend'],
            'resendWaitSeconds' => $canResendInfo['waitSeconds'],
            'resendMessage' => $canResendInfo['message'],
        ]);
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function resendVerification(Request $request): Response
    {
        $user = $this->getUser();

        // If already verified, redirect
        if ($user->isEmailVerified()) {
            $this->addFlash('info', 'Votre adresse email est déjà vérifiée.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Get and update resend attempts
        $session = $request->getSession();
        $resendAttempts = $session->get(self::SESSION_RESEND_ATTEMPTS_KEY, []);

        // Check rate limiting
        $canResendInfo = $this->emailVerificationService->canResendVerification($user, $resendAttempts);
        
        if (!$canResendInfo['canResend']) {
            $this->addFlash('warning', $canResendInfo['message']);
            return $this->redirectToRoute('app_verify_email');
        }

        // Record this attempt
        $resendAttempts[] = time();
        $session->set(self::SESSION_RESEND_ATTEMPTS_KEY, $resendAttempts);

        // Regenerate and send new code
        try {
            $this->emailVerificationService->regenerateAndSendCode($user);
            $this->addFlash('success', 'Un nouveau code de vérification a été envoyé à votre adresse email.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer plus tard.');
        }

        return $this->redirectToRoute('app_verify_email');
    }

    #[Route('/skip-verification', name: 'app_skip_verification', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function skipVerification(): Response
    {
        $this->addFlash('info', 'Vérification ignorée. Vous pouvez vérifier votre email à tout moment depuis les paramètres de sécurité.');
        return $this->redirectToRoute('app_profile_security');
    }
}
