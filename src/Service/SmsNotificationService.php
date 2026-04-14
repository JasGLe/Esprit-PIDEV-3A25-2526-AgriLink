<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SmsNotificationService
{
    public function __construct(
        private TwilioSmsService $twilioService,
        #[Autowire('%env(bool:TWILIO_SMS_ALERTS_ENABLED)%')]
        private bool $alertsEnabled = false,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Send a login notification SMS to the user.
     * Used to alert the user of new logins from different locations.
     *
     * @param User $user The user who logged in
     * @param string $ipAddress The IP address of the login
     * @param string $deviceInfo Additional device information (optional)
     * @return bool True if sent successfully
     */
    public function notifyNewLogin(User $user, string $ipAddress, string $deviceInfo = ''): bool
    {
        if (!$this->alertsEnabled || !$this->hasPhoneNumber($user)) {
            return false;
        }

        $message = sprintf(
            'New login to your AgriLink account from IP: %s %s',
            $ipAddress,
            $deviceInfo ? '(' . $deviceInfo . ')' : ''
        );

        return $this->twilioService->sendSecurityAlert(
            $user->getTelephone(),
            'login',
            $ipAddress . ($deviceInfo ? ' - ' . $deviceInfo : '')
        );
    }

    /**
     * Send a failed login attempt notification SMS.
     * Used to alert on multiple failed attempts.
     *
     * @param User $user The user experiencing failed attempts
     * @param int $attemptCount The number of failed attempts
     * @return bool True if sent successfully
     */
    public function notifyFailedLoginAttempts(User $user, int $attemptCount = 3): bool
    {
        if (!$this->alertsEnabled || !$this->hasPhoneNumber($user)) {
            return false;
        }

        $message = sprintf(
            'Alert: %d failed login attempts on your AgriLink account. If this wasn\'t you, change your password immediately.',
            $attemptCount
        );

        return $this->twilioService->sendSms($user->getTelephone(), $message);
    }

    /**
     * Send account locked notification SMS.
     * Used when account is locked due to security concerns.
     *
     * @param User $user The user whose account was locked
     * @param int $lockDurationMinutes How long the account is locked (optional)
     * @return bool True if sent successfully
     */
    public function notifyAccountLocked(User $user, int $lockDurationMinutes = 30): bool
    {
        if (!$this->alertsEnabled || !$this->hasPhoneNumber($user)) {
            return false;
        }

        $message = sprintf(
            'Your AgriLink account has been temporarily locked for security. It will be unlocked in %d minutes. Contact support if needed.',
            $lockDurationMinutes
        );

        return $this->twilioService->sendSms($user->getTelephone(), $message);
    }

    /**
     * Send email change notification SMS.
     * Used to alert user when their email is changed.
     *
     * @param User $user The user whose email was changed
     * @param string $oldEmail The previous email address
     * @return bool True if sent successfully
     */
    public function notifyEmailChanged(User $user, string $oldEmail): bool
    {
        if (!$this->alertsEnabled || !$this->hasPhoneNumber($user)) {
            return false;
        }

        return $this->twilioService->sendSecurityAlert(
            $user->getTelephone(),
            'email_changed',
            'Changed from ' . substr($oldEmail, 0, 3) . '***'
        );
    }

    /**
     * Send password change notification SMS.
     * Used to alert user when their password is changed.
     *
     * @param User $user The user whose password was changed
     * @return bool True if sent successfully
     */
    public function notifyPasswordChanged(User $user): bool
    {
        if (!$this->alertsEnabled || !$this->hasPhoneNumber($user)) {
            return false;
        }

        return $this->twilioService->sendSecurityAlert(
            $user->getTelephone(),
            'password_changed',
            'If this wasn\'t you, contact support immediately'
        );
    }

    /**
     * Send phone number verification code via SMS.
     * Used when user adds a new phone number.
     *
     * @param string $phoneNumber The phone number to verify
     * @param string $verificationCode The verification code
     * @return bool True if sent successfully
     */
    public function sendPhoneVerificationCode(string $phoneNumber, string $verificationCode): bool
    {
        if (!$this->twilioService->isEnabled()) {
            return false;
        }

        $message = sprintf(
            'Your AgriLink phone verification code is: %s (expires in 10 minutes)',
            $verificationCode
        );

        return $this->twilioService->sendSms($phoneNumber, $message);
    }

    /**
     * Check if SMS alerts are enabled.
     *
     * @return bool True if SMS alerts feature is enabled
     */
    public function isAlertsEnabled(): bool
    {
        return $this->alertsEnabled && $this->twilioService->isEnabled();
    }

    /**
     * Check if user has a valid phone number for SMS notifications.
     *
     * @param User $user The user to check
     * @return bool True if user has a phone number
     */
    private function hasPhoneNumber(User $user): bool
    {
        return !empty($user->getTelephone()) && $user->isPhoneVerified();
    }

    /**
     * Get SMS alert status for a specific user.
     * Returns both system-wide status and user-specific settings.
     *
     * @param User $user The user to check
     * @return array Status information
     */
    public function getAlertStatus(User $user): array
    {
        return [
            'alerts_enabled' => $this->alertsEnabled,
            'twilio_enabled' => $this->twilioService->isEnabled(),
            'user_has_phone' => !empty($user->getTelephone()),
            'user_verified_phone' => $user->isPhoneVerified(),
            'can_receive_alerts' => $this->isAlertsEnabled() && $this->hasPhoneNumber($user),
        ];
    }
}
