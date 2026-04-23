<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\RegistrationAgriculteurType;
use App\Form\UserManagement\RegistrationAgriPlusType;
use App\Form\UserManagement\RegistrationFormType;
use App\Form\UserManagement\RegistrationFournisseurType;
use App\Service\EmailVerificationService;
use App\Service\RecaptchaService;
use App\Service\VoiceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private RecaptchaService $recaptchaService,
        private VoiceRecognitionService $voiceRecognitionService,
        private string $recaptchaSiteKey,
    ) {
    }
    #[Route('/register', name: 'app_register')]
    public function registerChoice(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('user_management/security/register_choice.html.twig');
    }

    #[Route('/register/agriculteur', name: 'app_register_agriculteur')]
    public function registerAgriculteur(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationAgriculteurType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate reCAPTCHA v3
            $recaptchaToken = $request->request->get('g-recaptcha-response', '');
            if ($recaptchaToken && !$this->recaptchaService->verify($recaptchaToken, 'register')) {
                $this->addFlash('error', 'La vérification reCAPTCHA a échoué. Veuillez réessayer.');
                return $this->redirectToRoute('app_register_agriculteur');
            }

            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set role to AGRICULTEUR (single string for DB)
            $user->setRole(User::ROLE_AGRICULTEUR);
            $user->setIsActive(true);
            $user->setEmailVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate and send verification email
            try {
                $this->emailVerificationService->generateVerificationCode($user);
                $this->emailVerificationService->sendVerificationEmail($user);
                $this->addFlash('success', 'Votre compte Agriculteur a été créé avec succès ! Un code de vérification a été envoyé à votre email.');
            } catch (\Exception $e) {
                $this->addFlash('success', 'Votre compte Agriculteur a été créé avec succès ! Bienvenue sur AgriLink.');
            }

            // Auto-login après inscription and redirect to verification choice
            // Set session flag to skip notification creation during registration auto-login
            $request->getSession()->set('skip_login_notifications', true);
            $security->login($user, 'App\\Security\\UserAuthenticator', 'main');
            return $this->redirectToRoute('app_verification_choice');
        }

        return $this->render('user_management/security/register_agriculteur.html.twig', [
            'registrationForm' => $form,
            'recaptcha_site_key' => $this->recaptchaSiteKey,
        ]);
    }

    #[Route('/register/fournisseur', name: 'app_register_fournisseur')]
    public function registerFournisseur(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFournisseurType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate reCAPTCHA v3
            $recaptchaToken = $request->request->get('g-recaptcha-response', '');
            if ($recaptchaToken && !$this->recaptchaService->verify($recaptchaToken, 'register')) {
                $this->addFlash('error', 'La vérification reCAPTCHA a échoué. Veuillez réessayer.');
                return $this->redirectToRoute('app_register_fournisseur');
            }

            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set role to FOURNISSEUR (single string for DB)
            $user->setRole(User::ROLE_FOURNISSEUR);
            $user->setIsActive(true);
            $user->setEmailVerified(false);

            // Handle type-specific fields based on fournisseurTypeFournisseur
            $fournisseurType = $user->getFournisseurTypeFournisseur();
            
            if ($fournisseurType === User::FOURNISSEUR_PERSONNE) {
                // For PERSONNE: clear SOCIETE-specific fields
                $user->setFournisseurRaisonSocial(null);
                $user->setFournisseurNumRegistre(null);
                $user->setFournisseurFormeJuridique(null);
                $user->setFournisseurCapital(null);
            } elseif ($fournisseurType === User::FOURNISSEUR_SOCIETE) {
                // For SOCIETE: clear PERSONNE-specific fields
                $user->setFournisseurCin(null);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate and send verification email
            try {
                $this->emailVerificationService->generateVerificationCode($user);
                $this->emailVerificationService->sendVerificationEmail($user);
                $this->addFlash('success', 'Votre compte Fournisseur a été créé avec succès ! Un code de vérification a été envoyé à votre email.');
            } catch (\Exception $e) {
                $this->addFlash('success', 'Votre compte Fournisseur a été créé avec succès ! Bienvenue sur AgriLink.');
            }

            // Auto-login after registration and redirect to verification choice
            // Set session flag to skip notification creation during registration auto-login
            $request->getSession()->set('skip_login_notifications', true);
            $security->login($user, 'App\\Security\\UserAuthenticator', 'main');
            return $this->redirectToRoute('app_verification_choice');
        }

        return $this->render('user_management/security/register_fournisseur.html.twig', [
            'registrationForm' => $form,
            'recaptcha_site_key' => $this->recaptchaSiteKey,
        ]);
    }

    #[Route('/register/agriplus', name: 'app_register_agriplus')]
    public function registerAgriPlus(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationAgriPlusType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate reCAPTCHA v3
            $recaptchaToken = $request->request->get('g-recaptcha-response', '');
            if ($recaptchaToken && !$this->recaptchaService->verify($recaptchaToken, 'register')) {
                $this->addFlash('error', 'La vérification reCAPTCHA a échoué. Veuillez réessayer.');
                return $this->redirectToRoute('app_register_agriplus');
            }

            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set role to AGRIPLUS (single string for DB)
            $user->setRole(User::ROLE_AGRIPLUS);
            $user->setIsActive(true);
            $user->setEmailVerified(false);

            // Set subscription expiration to 1 month from now
            $expirationDate = new \DateTime();
            $expirationDate->modify('+1 month');
            $user->setAgriplusDateExpiration($expirationDate);

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate and send verification email
            try {
                $this->emailVerificationService->generateVerificationCode($user);
                $this->emailVerificationService->sendVerificationEmail($user);
                $this->addFlash('success', 'Votre compte AgriPlus a été créé avec succès ! Un code de vérification a été envoyé à votre email.');
            } catch (\Exception $e) {
                $this->addFlash('success', 'Votre compte AgriPlus a été créé avec succès ! Bienvenue parmi nos membres premium.');
            }

            // Auto-login after registration and redirect to verification choice
            // Set session flag to skip notification creation during registration auto-login
            $request->getSession()->set('skip_login_notifications', true);
            $security->login($user, 'App\\Security\\UserAuthenticator', 'main');
            return $this->redirectToRoute('app_verification_choice');
        }

        return $this->render('user_management/security/register_agriplus.html.twig', [
            'registrationForm' => $form,
            'recaptcha_site_key' => $this->recaptchaSiteKey,
        ]);
    }

    #[Route('/register/voice/status', name: 'app_register_voice_status', methods: ['GET'])]
    public function voiceStatus(): JsonResponse
    {
        $user = $this->getUser();
        $isEnrolled = $user instanceof User && $user->getVoiceEmbedding() !== null;

        return $this->json([
            'enrolled' => $isEnrolled,
            'user_id' => $user instanceof User ? $user->getId() : null,
        ]);
    }

    #[Route('/register/voice/enroll', name: 'app_register_voice_enroll', methods: ['POST'])]
    public function voiceEnroll(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['embedding']) || !is_array($data['embedding'])) {
                return $this->json(['error' => 'Invalid voice embedding'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['embedding'])) {
                return $this->json(['error' => 'Voice embedding array is empty'], Response::HTTP_BAD_REQUEST);
            }

            $audioDuration = $data['audio_duration'] ?? 0;
            if ($audioDuration < 3 || $audioDuration > 5) {
                return $this->json(['error' => 'Audio duration must be 3-5 seconds'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
            }

            // Increment enrollment attempts (max 3)
            $attempts = $user->getVoiceEnrollmentAttempts();
            if ($attempts >= 3) {
                return $this->json(['error' => 'Maximum enrollment attempts reached'], Response::HTTP_TOO_MANY_REQUESTS);
            }

            // Store the voice embedding
            $embeddingJson = $this->voiceRecognitionService->storeVoiceEmbedding($data['embedding']);
            $user->setVoiceEmbedding($embeddingJson);
            $user->setVoiceEnrolledAt(new \DateTime());
            $user->setVoiceEnrollmentAttempts($attempts + 1);

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Votre voix a été enregistrée avec succès.',
                'enrolled_at' => $user->getVoiceEnrolledAt()?->format('Y-m-d H:i:s'),
                'quality_score' => 0.85,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Enrollment failed: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/register/voice/verify', name: 'app_register_voice_verify', methods: ['POST'])]
    public function voiceVerify(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['embedding']) || !is_array($data['embedding'])) {
                return $this->json(['error' => 'Invalid voice embedding'], Response::HTTP_BAD_REQUEST);
            }

            // Find users with enrolled voice using query builder
            $userRepository = $entityManager->getRepository(User::class);
            $qb = $userRepository->createQueryBuilder('u');
            $enrolledUsers = $qb->where('u.voiceEmbedding IS NOT NULL')
                ->getQuery()
                ->getResult();

            $matchedUser = null;
            $minDistance = \PHP_FLOAT_MAX;
            $threshold = $this->voiceRecognitionService->getThreshold();

            foreach ($enrolledUsers as $enrolledUser) {
                $storedEmbedding = $enrolledUser->getVoiceEmbedding();
                if (!$storedEmbedding) {
                    continue;
                }

                $storedArray = json_decode($storedEmbedding, true);
                if (!is_array($storedArray)) {
                    continue;
                }

                $distance = $this->voiceRecognitionService->euclideanDistance(
                    $storedArray,
                    $data['embedding']
                );

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    if ($distance <= $threshold) {
                        $matchedUser = $enrolledUser;
                    }
                }
            }

            if ($matchedUser) {
                return $this->json([
                    'verified' => true,
                    'user_id' => $matchedUser->getId(),
                    'matched_distance' => round($minDistance, 4),
                    'threshold' => $threshold,
                    'confidence' => round((1 - ($minDistance / $threshold)) * 100, 2),
                ]);
            }

            return $this->json([
                'verified' => false,
                'message' => 'No voice match found',
                'matched_distance' => round($minDistance, 4),
                'threshold' => $threshold,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Verification failed: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }
}
