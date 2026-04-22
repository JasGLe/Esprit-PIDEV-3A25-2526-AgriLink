<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Service\PhoneVerificationService;
use App\Service\TwilioSmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PhoneVerificationController extends AbstractController
{
    public function __construct(
        private PhoneVerificationService $phoneVerificationService,
        private TwilioSmsService $twilioService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Show phone number input form for verification.
     */
    #[Route('/verify-phone', name: 'app_verify_phone', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function showPhoneForm(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // If already verified, redirect to dashboard
        if ($user->isPhoneVerified()) {
            $this->addFlash('info', 'Votre numéro de téléphone est déjà vérifié.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Get source (registration or profile security)
        $source = $request->query->get('source', 'registration');

        return $this->render('user_management/verify_phone.html.twig', [
            'source' => $source,
            'phone' => $user->getTelephone() ?? '',
        ]);
    }

    /**
     * Send SMS OTP code to the provided phone number.
     */
    #[Route('/verify-phone/send', name: 'app_send_phone_otp', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function sendOtp(Request $request): Response
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->redirectToRoute('app_login');
            }

            $phone = $request->request->get('phone');

            // Validate phone number format
            if (empty($phone)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le numéro de téléphone est requis.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Format and validate phone number (Tunisia)
            $formattedPhone = $this->twilioService->formatPhoneNumber($phone);

            if (!$this->twilioService->isValidPhoneNumber($formattedPhone)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Format de numéro de téléphone invalide. Utilisez un format Tunisien (+216XXXXXXXX).',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check cooldown
            if (!$this->phoneVerificationService->canResendVerification($user)) {
                $cooldown = $this->phoneVerificationService->getRemainingCooldown($user);
                return $this->json([
                    'success' => false,
                    'message' => sprintf(
                        'Veuillez patienter %d secondes avant de renvoyer le code.',
                        $cooldown
                    ),
                    'cooldown' => $cooldown,
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            // Store phone number on user
            $user->setTelephone($formattedPhone);

            // Generate new OTP code
            $code = $this->phoneVerificationService->generateVerificationCode($user);

            // Send SMS
            $smsSent = $this->phoneVerificationService->sendVerificationSms($user);

            if (!$smsSent) {
                $this->addFlash('error', 'Impossible d\'envoyer le SMS. Veuillez réessayer.');
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible d\'envoyer le SMS. Veuillez réessayer.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Success
            return $this->json([
                'success' => true,
                'message' => sprintf(
                    'Code de vérification envoyé au numéro %s',
                    $this->maskPhoneNumber($formattedPhone)
                ),
                'phone' => $this->maskPhoneNumber($formattedPhone),
                'expires_in' => $this->phoneVerificationService->getOtpExpirationSeconds($user),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi du code. Veuillez réessayer.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify the OTP code entered by the user.
     */
    #[Route('/verify-phone/verify', name: 'app_verify_phone_code', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function verifyCode(Request $request): Response
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->redirectToRoute('app_login');
            }

            $code = $request->request->get('code');

            // Validate code input
            if (empty($code)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le code de vérification est requis.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if OTP has expired
            if ($this->phoneVerificationService->isOtpExpired($user)) {
                $this->phoneVerificationService->resetOtp($user);
                return $this->json([
                    'success' => false,
                    'message' => 'Le code de vérification a expiré. Veuillez en demander un nouveau.',
                    'expired' => true,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Verify code
            if ($this->phoneVerificationService->verifyCode($user, $code)) {
                $this->addFlash('success', 'Votre numéro de téléphone a été vérifié avec succès.');
                return $this->json([
                    'success' => true,
                    'message' => 'Numéro de téléphone vérifié avec succès.',
                    'redirect' => $this->generateUrl('app_dashboard'),
                ]);
            }

            // Invalid code
            $remaining = $this->phoneVerificationService->getRemainingAttempts($user);

            if ($remaining === 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Trop de tentatives. Le code a été invalidé. Veuillez en demander un nouveau.',
                    'expired' => true,
                ], Response::HTTP_BAD_REQUEST);
            }

            return $this->json([
                'success' => false,
                'message' => sprintf(
                    'Code de vérification incorrect. %d tentative(s) restante(s).',
                    $remaining
                ),
                'remaining_attempts' => $remaining,
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification du code. Veuillez réessayer.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Skip phone verification during registration and continue.
     */
    #[Route('/skip-phone-verification', name: 'app_skip_phone_verification', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function skipPhoneVerification(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Get source (registration or profile security)
        $source = $request->query->get('source', 'registration');

        // Reset any pending OTP
        if ($user->getOtpCode() !== null) {
            $this->phoneVerificationService->resetOtp($user);
        }

        // Log skip event
        // TODO: Consider adding verification skip logging here

        // Redirect based on source
        if ($source === 'profile') {
            $this->addFlash('info', 'Vérification du téléphone ignorée.');
            return $this->redirectToRoute('app_profile_security');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Verify phone during post-login from security menu.
     * Used when accessing from ProfileSecurityController.
     */
    #[Route('/profile/verify-phone', name: 'app_profile_verify_phone', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function verifyFromProfile(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // If already verified, redirect back
        if ($user->isPhoneVerified()) {
            $this->addFlash('info', 'Votre numéro de téléphone est déjà vérifié.');
            return $this->redirectToRoute('app_profile_security');
        }

        return $this->render('user_management/verify_phone.html.twig', [
            'source' => 'profile',
            'phone' => $user->getTelephone() ?? '',
        ]);
    }

    /**
     * Mask phone number for display (show only last 4 digits).
     * Example: +21698765432 becomes +216****5432
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return $phone;
        }

        $lastFour = substr($phone, -4);
        $masked = str_repeat('*', strlen($phone) - 4) . $lastFour;

        return $masked;
    }
}
