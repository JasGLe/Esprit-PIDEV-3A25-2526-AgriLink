<?php

namespace App\Controller\UserManagement;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private string $recaptchaSiteKey,
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        
        // Get remembered email from cookie
        $rememberedEmail = $request->cookies->get('remembered_email', '');

        return $this->render('user_management/security/login.html.twig', [
            'last_username' => $lastUsername ?: $rememberedEmail,
            'remembered_email' => $rememberedEmail,
            'error' => $error,
            'recaptcha_site_key' => $this->recaptchaSiteKey,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
