<?php

namespace App\Security;

use App\Entity\UserManagement\User;
use App\Service\RecaptchaService;
use App\Service\TwoFactorService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TwoFactorService $twoFactorService,
        private TokenStorageInterface $tokenStorage,
        private RecaptchaService $recaptchaService,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('_username');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // Validate reCAPTCHA v3
        $recaptchaToken = $request->request->get('g-recaptcha-response', '');
        if ($recaptchaToken && !$this->recaptchaService->verify($recaptchaToken, 'login')) {
            throw new CustomUserMessageAuthenticationException(
                'La vérification reCAPTCHA a échoué. Veuillez réessayer.'
            );
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('_password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->isTwoFactorEnabled()) {
            $session = $request->getSession();

            // Store pending 2FA user ID
            $session->set('_2fa_pending_user', $user->getId());

            // Preserve the original target path so we can redirect after 2FA
            if ($targetPath = $this->getTargetPath($session, $firewallName)) {
                $session->set('_security.main.target_path', $targetPath);
            }

            // Revoke authentication until 2FA is complete
            $session->remove('_security_main');
            $this->tokenStorage->setToken(null);

            // Generate and send OTP
            $this->twoFactorService->generateOtp($user);
            $this->twoFactorService->sendOtpEmail($user);

            return new RedirectResponse($this->urlGenerator->generate('app_2fa_verify'));
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
