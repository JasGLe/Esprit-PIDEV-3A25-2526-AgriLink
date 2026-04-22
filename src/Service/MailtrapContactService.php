<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MailtrapContactService
{
    private const MAILTRAP_SEND_API = 'https://sandbox.api.mailtrap.io/api/send/3512866';
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $mailtrapApiToken,
        private readonly string $mailtrapFromEmail,
        private readonly string $mailtrapFromName,
        private readonly string $adminContactEmail,
    ) {
    }

    public function sendBanAppeal(User $user, string $subject, string $message): bool
    {
        $token = trim($this->mailtrapApiToken);
        if (
            $token === ''
            || trim($this->mailtrapFromEmail) === ''
            || trim($this->adminContactEmail) === ''
        ) {
            $this->logger->warning('[MailtrapContact] Missing Mailtrap configuration.');
            return false;
        }
        if (str_starts_with($token, 'smtp://')) {
            $this->logger->warning('[MailtrapContact] Invalid MAILTRAP_API_TOKEN format: SMTP DSN provided instead of API token.');
            return false;
        }

        $subject = trim($subject);
        $message = trim($message);
        if ($subject === '' || $message === '') {
            return false;
        }

        $bodyText = sprintf(
            "Ban appeal request from AgriLink user\n\nUser: %s\nEmail: %s\nRole: %s\nReason in account: %s\n\nMessage:\n%s\n",
            $user->getDisplayName(),
            (string) $user->getEmail(),
            $user->getRole(),
            (string) ($user->getBanReason() ?? '-'),
            $message
        );

        $payload = [
            'from' => [
                'email' => $this->mailtrapFromEmail,
                'name' => $this->mailtrapFromName !== '' ? $this->mailtrapFromName : 'AgriLink',
            ],
            'to' => [
                ['email' => $this->adminContactEmail],
            ],
            'subject' => '[Ban Appeal] '.$subject,
            'text' => $bodyText,
            'category' => 'ban-appeal',
        ];

        try {
            $response = $this->httpClient->request('POST', self::MAILTRAP_SEND_API, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10.0,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->error('[MailtrapContact] Mailtrap API rejected request.', [
                    'status' => $status,
                    'response' => $response->getContent(false),
                    'from' => $this->mailtrapFromEmail,
                    'to' => $this->adminContactEmail,
                ]);
                return false;
            }

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[MailtrapContact] Transport error while sending.', [
                'message' => $e->getMessage(),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('[MailtrapContact] Unexpected send error.', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

