<?php

namespace App\Service;

use App\Entity\UserManagement\SecurityEvent;
use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SecurityEventService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log a security event with IP, user agent, and optional details
     */
    public function log(
        string $eventType,
        ?User $user = null,
        ?string $details = null
    ): SecurityEvent {
        $securityEvent = new SecurityEvent();
        $securityEvent->setEventType($eventType);
        $securityEvent->setUser($user);

        // Build details JSON with IP, user agent, and any additional context
        $detailsArray = $this->buildBaseDetails();
        
        if ($details !== null) {
            $decoded = json_decode($details, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $detailsArray = array_merge($detailsArray, $decoded);
            } else {
                $detailsArray['context'] = $details;
            }
        }

        $securityEvent->setDetails(json_encode($detailsArray, JSON_UNESCAPED_UNICODE));

        $this->em->persist($securityEvent);
        $this->em->flush();

        return $securityEvent;
    }

    /**
     * Log a successful login
     */
    public function logLoginSuccess(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_LOGIN_SUCCESS,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log a failed login attempt
     */
    public function logLoginFailed(?string $email, string $reason): void
    {
        // Try to find the user by email for association (if exists)
        $user = null;
        if ($email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        }

        $this->log(
            SecurityEvent::EVENT_LOGIN_FAILED,
            $user,
            json_encode([
                'email' => $email,
                'reason' => $reason
            ])
        );
    }

    /**
     * Log account locked due to too many failed attempts
     */
    public function logAccountLocked(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_ACCOUNT_LOCKED,
            $user,
            json_encode([
                'email' => $user->getEmail(),
                'locked_until' => $user->getLockedUntil()?->format('Y-m-d H:i:s')
            ])
        );
    }

    /**
     * Log when 2FA OTP is sent to user
     */
    public function log2FAOtpSent(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_2FA_OTP_SENT,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log successful 2FA verification
     */
    public function log2FASuccess(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_2FA_SUCCESS,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log failed 2FA verification
     */
    public function log2FAFailed(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_2FA_FAILED,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log password change
     */
    public function logPasswordChanged(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_PASSWORD_CHANGED,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log email verification
     */
    public function logEmailVerified(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_EMAIL_VERIFIED,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log OAuth login (returning user)
     */
    public function logOAuthLogin(User $user, string $provider): void
    {
        $this->log(
            SecurityEvent::EVENT_OAUTH_LOGIN,
            $user,
            json_encode([
                'email' => $user->getEmail(),
                'provider' => $provider
            ])
        );
    }

    /**
     * Log OAuth registration (new user)
     */
    public function logOAuthRegister(User $user, string $provider): void
    {
        $this->log(
            SecurityEvent::EVENT_OAUTH_REGISTER,
            $user,
            json_encode([
                'email' => $user->getEmail(),
                'provider' => $provider
            ])
        );
    }

    /**
     * Log session revocation
     */
    public function logSessionRevoked(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_SESSION_REVOKED,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log user logout
     */
    public function logLogout(User $user): void
    {
        $this->log(
            SecurityEvent::EVENT_LOGOUT,
            $user,
            json_encode(['email' => $user->getEmail()])
        );
    }

    /**
     * Log suspicious activity detection
     */
    public function logSuspiciousActivity(User $user, string $reason): void
    {
        $this->log(
            SecurityEvent::EVENT_SUSPICIOUS_ACTIVITY,
            $user,
            json_encode([
                'email' => $user->getEmail(),
                'reason' => $reason
            ])
        );
    }

    /**
     * Build base details array with IP and user agent from current request
     */
    private function buildBaseDetails(): array
    {
        $details = [
            'ip_address' => null,
            'user_agent' => null,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        $request = $this->requestStack->getCurrentRequest();
        
        if ($request !== null) {
            $details['ip_address'] = $request->getClientIp();
            $details['user_agent'] = $request->headers->get('User-Agent');
        }

        return $details;
    }
}
