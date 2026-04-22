<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\RegistrationAgriculteurType;
use App\Form\UserManagement\RegistrationAgriPlusType;
use App\Form\UserManagement\RegistrationFormType;
use App\Form\UserManagement\RegistrationFournisseurType;
use App\Service\EmailVerificationService;
use App\Service\RecaptchaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private RecaptchaService $recaptchaService,
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
}
