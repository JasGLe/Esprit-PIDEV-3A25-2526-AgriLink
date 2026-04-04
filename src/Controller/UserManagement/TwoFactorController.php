<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TwoFactorController extends AbstractController
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('/2fa/verify', name: 'app_2fa_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_2fa_pending_user');

        // If no pending 2FA, redirect to login
        if (!$userId) {
            $this->addFlash('error', 'Session expirée. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_login');
        }

        // Get the user
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            $session->remove('_2fa_pending_user');
            $this->addFlash('error', 'Utilisateur introuvable. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_login');
        }

        $error = null;
        $success = false;

        // Handle POST (OTP verification)
        if ($request->isMethod('POST')) {
            $code = $request->request->get('otp_code', '');
            
            // Clean up the code (remove spaces, dashes)
            $code = preg_replace('/[^0-9]/', '', $code);
            
            if (empty($code)) {
                $error = 'Veuillez entrer le code de vérification.';
            } elseif ($this->twoFactorService->isOtpExpired($user)) {
                $error = 'Le code a expiré. Veuillez demander un nouveau code.';
            } elseif ($this->twoFactorService->getRemainingAttempts($user) <= 0) {
                $error = 'Nombre maximum de tentatives atteint. Veuillez demander un nouveau code.';
            } elseif ($this->twoFactorService->verifyOtp($user, $code)) {
                // Success! Complete the login
                $success = true;
                $session->remove('_2fa_pending_user');
                
                // Mark 2FA as verified in this session (critical for defense-in-depth)
                $session->set('_2fa_verified', true);
                
                // Create authentication token
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                $this->tokenStorage->setToken($token);
                
                // Save token to session
                $session->set('_security_main', serialize($token));
                
                // Dispatch interactive login event
                $event = new InteractiveLoginEvent($request, $token);
                $this->eventDispatcher->dispatch($event);
                
                // Redirect to dashboard or target path
                $targetPath = $session->get('_security.main.target_path');
                if ($targetPath) {
                    $session->remove('_security.main.target_path');
                    return $this->redirect($targetPath);
                }
                
                return $this->redirectToRoute('app_dashboard');
            } else {
                $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);
                if ($remainingAttempts > 0) {
                    $error = sprintf(
                        'Code incorrect. Il vous reste %d tentative%s.',
                        $remainingAttempts,
                        $remainingAttempts > 1 ? 's' : ''
                    );
                } else {
                    $error = 'Nombre maximum de tentatives atteint. Veuillez demander un nouveau code.';
                }
            }
        }

        // Calculate remaining time and attempts for template
        $expirationSeconds = $this->twoFactorService->getOtpExpirationSeconds($user);
        $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);
        $canResend = $this->twoFactorService->canResendOtp($user);
        $cooldownSeconds = $this->twoFactorService->getRemainingCooldown($user);

        return $this->render('user_management/security/verify_2fa.html.twig', [
            'error' => $error,
            'user' => $user,
            'expiration_seconds' => $expirationSeconds,
            'remaining_attempts' => $remainingAttempts,
            'max_attempts' => $this->twoFactorService->getMaxAttempts(),
            'can_resend' => $canResend,
            'cooldown_seconds' => $cooldownSeconds,
        ]);
    }

    #[Route('/2fa/resend', name: 'app_2fa_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_2fa_pending_user');

        if (!$userId) {
            return $this->json([
                'success' => false,
                'message' => 'Session expirée. Veuillez vous reconnecter.',
                'redirect' => $this->generateUrl('app_login')
            ]);
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            $session->remove('_2fa_pending_user');
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
                'redirect' => $this->generateUrl('app_login')
            ]);
        }

        // Check cooldown
        if (!$this->twoFactorService->canResendOtp($user)) {
            $cooldown = $this->twoFactorService->getRemainingCooldown($user);
            return $this->json([
                'success' => false,
                'message' => sprintf('Veuillez attendre %d secondes avant de renvoyer le code.', $cooldown),
                'cooldown' => $cooldown
            ]);
        }

        // Generate and send new OTP
        try {
            $this->twoFactorService->generateOtp($user);
            $this->twoFactorService->sendOtpEmail($user);
            
            return $this->json([
                'success' => true,
                'message' => 'Un nouveau code a été envoyé à votre adresse email.',
                'expiration_seconds' => $this->twoFactorService->getOtpExpirationSeconds($user),
                'cooldown_seconds' => $this->twoFactorService->getCooldownSeconds()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du code. Veuillez réessayer.'
            ]);
        }
    }

    #[Route('/2fa/cancel', name: 'app_2fa_cancel', methods: ['GET'])]
    public function cancel(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_2fa_pending_user');

        if ($userId) {
            // Reset OTP for the user
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user) {
                $this->twoFactorService->resetOtp($user);
            }
        }

        // Clear session
        $session->remove('_2fa_pending_user');
        
        $this->addFlash('info', 'Connexion annulée.');
        return $this->redirectToRoute('app_login');
    }
}
