<?php

namespace App\EventSubscriber;

use App\Entity\UserManagement\User;
use App\Service\TwoFactorService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class TwoFactorAuthSubscriber implements EventSubscriberInterface
{
    /**
     * Routes that must remain accessible during a pending 2FA challenge.
     * Navigating to these routes will NOT trigger the 2FA enforcement redirect.
     */
    private const EXCLUDED_ROUTES = [
        'app_login', 'app_logout',
        'app_2fa_verify', 'app_2fa_resend', 'app_2fa_cancel',
        'app_register', 'app_register_agriculteur', 'app_register_fournisseur', 'app_register_agriplus',
        'app_forgot_password', 'app_forgot_password_sent', 'app_reset_password',
        'app_verify_email', 'app_resend_verification', 'app_skip_verification',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TwoFactorService $twoFactorService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 0],
            KernelEvents::REQUEST => ['onKernelRequest', -128],
        ];
    }

    /**
     * Fired during form login BEFORE authentication is finalised.
     *
     * Prevents the REMEMBERME cookie from being created for users who have 2FA
     * enabled. Without this, the cookie allows Symfony's RememberMeAuthenticator
     * to bypass onAuthenticationSuccess() — and therefore bypass 2FA — on every
     * subsequent request that carries the cookie.
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        if (!$passport->hasBadge(RememberMeBadge::class)) {
            return;
        }

        $user = $passport->getUser();

        if ($user instanceof User && $user->isTwoFactorEnabled()) {
            $passport->getBadge(RememberMeBadge::class)->disable();
        }
    }

    /**
     * Fired on every main request, after the security firewall has run.
     *
     * Catches the case where a user with 2FA is authenticated (e.g. via an
     * existing REMEMBERME cookie from before 2FA was enabled, or any other
     * authenticator that bypasses onAuthenticationSuccess) but has not yet
     * completed the 2FA challenge in the current session.
     *
     * Revokes the token, schedules a new OTP if needed, and redirects to the
     * 2FA verification page. The REMEMBERME cookie is also expired on the
     * response to prevent repeated bypass attempts.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!($user instanceof User) || !$user->isTwoFactorEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // User has already completed 2FA in this session — nothing to do.
        if ($session->get('_2fa_verified')) {
            return;
        }

        // Allow the 2FA flow routes themselves (and public routes) to load normally.
        $route = $request->attributes->get('_route');
        if (in_array($route, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        // Revoke the bypass authentication so the session cannot re-authenticate.
        $session->remove('_security_main');
        $this->tokenStorage->setToken(null);

        // Mark the user as pending 2FA.
        $session->set('_2fa_pending_user', $user->getId());

        // Only generate a new OTP if there is no valid one already (avoids
        // spamming the user's inbox if they quickly navigate between pages).
        if ($this->twoFactorService->isOtpExpired($user)) {
            $this->twoFactorService->generateOtp($user);
            $this->twoFactorService->sendOtpEmail($user);
        }

        $response = new RedirectResponse($this->urlGenerator->generate('app_2fa_verify'));

        // Expire the REMEMBERME cookie so it cannot be used for another bypass
        // attempt. The user will receive a fresh cookie after completing 2FA if
        // they request one.
        $response->headers->clearCookie('REMEMBERME', '/');

        $event->setResponse($response);
    }
}
