<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class TwilioSmsService
{
    private ?Client $client = null;
    private bool $enabled;

    public function __construct(
        #[Autowire('%env(TWILIO_ACCOUNT_SID)%')]
        private string $accountSid = '',
        #[Autowire('%env(TWILIO_AUTH_TOKEN)%')]
        private string $authToken = '',
        #[Autowire('%env(TWILIO_PHONE_NUMBER)%')]
        private string $fromNumber = '',
        #[Autowire('%env(bool:TWILIO_ENABLED)%')]
        bool $enabled = false,
        private ?LoggerInterface $logger = null
    ) {
        $this->enabled = $enabled && !empty($accountSid) && !empty($authToken) && !empty($fromNumber);
        
        if ($this->enabled) {
            $this->client = new Client($accountSid, $authToken);
        }
    }

    /**
     * Send an SMS message to a phone number.
     *
     * @param string $phoneNumber The recipient's phone number (with or without +216)
     * @param string $message The SMS message content
     * @return bool True if sent successfully, false otherwise
     */
    public function sendSms(string $phoneNumber, string $message): bool
    {
        if (!$this->enabled) {
            $this->log('warning', 'Twilio SMS is not enabled. Message not sent to ' . $this->maskPhone($phoneNumber));
            return false;
        }

        // Format phone number (adds +216 if needed)
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);
        if (!$formattedPhone) {
            $this->log('error', 'Invalid phone number format: ' . $this->maskPhone($phoneNumber));
            return false;
        }

        if (mb_strlen($message) > 1600) {
            $this->log('error', 'Message exceeds 1600 character limit for ' . $this->maskPhone($formattedPhone));
            return false;
        }

        try {
            $this->client->messages->create(
                $formattedPhone,
                [
                    'from' => $this->fromNumber,
                    'body' => $message,
                ]
            );

            $this->log('info', 'SMS sent successfully to ' . $this->maskPhone($formattedPhone));
            return true;
        } catch (TwilioException $e) {
            $this->log('error', 'Twilio SMS error: ' . $e->getMessage() . ' for ' . $this->maskPhone($formattedPhone));
            return false;
        } catch (\Exception $e) {
            $this->log('error', 'Unexpected SMS error: ' . $e->getMessage() . ' for ' . $this->maskPhone($formattedPhone));
            return false;
        }
    }

    /**
     * Send an OTP code via SMS.
     *
     * @param string $phoneNumber The recipient's phone number
     * @param string $otpCode The 6-digit OTP code
     * @return bool True if sent successfully
     */
    public function sendOtpSms(string $phoneNumber, string $otpCode): bool
    {
        $message = sprintf(
            'Your AgriLink verification code is: %s (expires in 10 minutes)',
            $otpCode
        );
        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Send a security alert via SMS.
     *
     * @param string $phoneNumber The recipient's phone number
     * @param string $alertType The type of alert (e.g., 'login', 'failed_attempt', 'account_locked')
     * @param string $details Additional details about the alert
     * @return bool True if sent successfully
     */
    public function sendSecurityAlert(string $phoneNumber, string $alertType, string $details = ''): bool
    {
        $alertMessages = [
            'login' => 'New login to your AgriLink account',
            'failed_attempt' => 'Multiple failed login attempts on your account',
            'account_locked' => 'Your account has been temporarily locked due to security concerns',
            'email_changed' => 'Your email address has been changed',
            'password_changed' => 'Your password has been changed',
            'phone_added' => 'A new phone number has been added to your account',
        ];

        $baseMessage = $alertMessages[$alertType] ?? 'Security alert on your AgriLink account';
        $message = $baseMessage . ($details ? ': ' . $details : '');

        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Check if Twilio SMS is enabled and properly configured.
     *
     * @return bool True if enabled and ready to send
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Validate and format phone number for Tunisia.
     * Accepts numbers with or without +216 prefix.
     * Numbers without country code are assumed to be Tunisia (+216).
     *
     * @param string $phoneNumber The phone number (with or without +216)
     * @return string|null Formatted phone number with +216, or null if invalid
     */
    public function formatPhoneNumber(string $phoneNumber): ?string
    {
        // Remove any spaces, dashes, or parentheses
        $clean = preg_replace('/[\s\-\(\)]+/', '', $phoneNumber);
        if ($clean === null) {
            return null;
        }

        // If already in E.164 format with +216, return as-is
        if (preg_match('/^\+216\d{8}$/', $clean)) {
            return $clean;
        }

        // If starts with 216 (without +), add +
        if (preg_match('/^216\d{8}$/', $clean)) {
            return '+' . $clean;
        }

        // If just 8 digits (Tunisian number without country code), add +216
        if (preg_match('/^\d{8}$/', $clean)) {
            return '+216' . $clean;
        }

        // Invalid format
        return null;
    }

    /**
     * Validate phone number format (E.164 standard: +216[8 digits]).
     *
     * @param string $phoneNumber The phone number to validate
     * @return bool True if valid format
     */
    public function isValidPhoneNumber(string $phoneNumber): bool
    {
        // Tunisia format: +216 followed by 8 digits
        return (bool) preg_match('/^\+216\d{8}$/', $phoneNumber);
    }

    /**
     * Mask phone number for logging (show only last 4 digits).
     *
     * @param string $phoneNumber The phone number to mask
     * @return string The masked phone number
     */
    private function maskPhone(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 4) {
            return '****';
        }
        return '***' . substr($phoneNumber, -4);
    }

    /**
     * Log a message.
     *
     * @param string $level The log level (info, warning, error)
     * @param string $message The message to log
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            match ($level) {
                'info' => $this->logger->info($message, ['service' => 'TwilioSmsService']),
                'warning' => $this->logger->warning($message, ['service' => 'TwilioSmsService']),
                'error' => $this->logger->error($message, ['service' => 'TwilioSmsService']),
                default => null,
            };
        }
    }
}
