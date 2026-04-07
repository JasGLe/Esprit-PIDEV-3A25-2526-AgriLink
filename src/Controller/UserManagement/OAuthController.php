<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Entity\UserManagement\UserSession;
use App\Repository\UserManagement\UserRepository;
use App\Service\OAuthService;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/oauth', name: 'app_oauth_')]
class OAuthController extends AbstractController
{
    public function __construct(
        private OAuthService $oauthService,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TokenStorageInterface $tokenStorage,
        private SecurityEventService $securityEventService,
    ) {}

    /**
     * Redirect to Google OAuth
     */
    #[Route('/google', name: 'google')]
    public function googleLogin(): Response
    {
        return $this->redirect($this->oauthService->getGoogleAuthUrl());
    }

    /**
     * Handle Google OAuth callback
     */
    #[Route('/google/callback', name: 'google_callback')]
    public function googleCallback(Request $request): Response
    {
        // If already authenticated, skip to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('danger', 'Erreur OAuth: ' . $error);
            return $this->redirectToRoute('app_login');
        }

        if (!$code) {
            $this->addFlash('danger', 'Code d\'autorisation manquant');
            return $this->redirectToRoute('app_login');
        }

        $oauthUser = $this->oauthService->handleGoogleCallback($code);

        if (!$oauthUser) {
            $this->addFlash('danger', 'Échec de l\'authentification avec Google');
            return $this->redirectToRoute('app_login');
        }

        return $this->authenticateOrCreateUser($oauthUser, $request);
    }

    /**
     * Redirect to Facebook OAuth
     */
    #[Route('/facebook', name: 'facebook')]
    public function facebookLogin(): Response
    {
        return $this->redirect($this->oauthService->getFacebookAuthUrl());
    }

    /**
     * Handle Facebook OAuth callback
     */
    #[Route('/facebook/callback', name: 'facebook_callback')]
    public function facebookCallback(Request $request): Response
    {
        // If already authenticated, skip to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('danger', 'Erreur OAuth: ' . $error);
            return $this->redirectToRoute('app_login');
        }

        if (!$code) {
            $this->addFlash('danger', 'Code d\'autorisation manquant');
            return $this->redirectToRoute('app_login');
        }

        $oauthUser = $this->oauthService->handleFacebookCallback($code);

        if (!$oauthUser) {
            $this->addFlash('danger', 'Échec de l\'authentification avec Facebook');
            return $this->redirectToRoute('app_login');
        }

        return $this->authenticateOrCreateUser($oauthUser, $request);
    }

    /**
     * Authenticate or create user based on OAuth data
     */
    private function authenticateOrCreateUser(array $oauthUser, Request $request): Response
    {
        $isNewUser = false;

        // Check if user exists by OAuth provider ID
        $user = $this->userRepository->findOneBy([
            'oauthProvider' => $oauthUser['provider'],
            'oauthProviderId' => $oauthUser['provider_id'],
        ]);

        // If not found by OAuth ID, try to find by email
        if (!$user && $oauthUser['email']) {
            $user = $this->userRepository->findOneBy(['email' => $oauthUser['email']]);

            if ($user) {
                // Link OAuth account to existing user
                $user->setOauthProvider($oauthUser['provider']);
                $user->setOauthProviderId($oauthUser['provider_id']);
            }
        }

        // Create new user if doesn't exist
        if (!$user) {
            $isNewUser = true;
            $user = new User();
            $user->setEmail($oauthUser['email']);
            $user->setNom($oauthUser['name'] ?? 'OAuth User');
            $user->setPassword(''); // No password for OAuth users
            $user->setOauthProvider($oauthUser['provider']);
            $user->setOauthProviderId($oauthUser['provider_id']);
            $user->setEmailVerified(true); // OAuth providers verify email
            $user->setIsActive(true);
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
        }

        // Check if user is active
        if (!$user->isActive()) {
            $this->addFlash('danger', 'Votre compte a été désactivé.');
            $this->entityManager->flush();
            return $this->redirectToRoute('app_login');
        }

        $this->entityManager->flush();

        // Log to SecurityEvent for audit trail (for OAuth specifically)
        if ($isNewUser) {
            $this->securityEventService->logOAuthRegister($user, $oauthUser['provider']);
        } else {
            $this->securityEventService->logOAuthLogin($user, $oauthUser['provider']);
        }

        // Create token and authenticate user
        // Note: This manually sets the token and may not trigger LoginSuccessListener
        // So we also create the UserSession record here
        $userSession = new UserSession();
        $userSession->setUser($user);
        $userSession->setDeviceInfo($request->headers->get('User-Agent'));
        $userSession->setRefreshTokenHash(hash('sha256', (string)$request->getSession()->getId()));
        $userSession->setExpiresAt(new \DateTime('+30 days'));

        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );

        $this->tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        $this->addFlash('success', 'Connexion réussie avec ' . ucfirst($oauthUser['provider']));

        return $this->redirectToRoute('app_dashboard');
    }
}

