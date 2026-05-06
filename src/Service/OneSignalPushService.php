<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OneSignalPushService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $oneSignalAppId = '',
        private readonly string $oneSignalApiKey = '',
        private readonly bool $oneSignalEnabled = false,
    ) {
    }

    /**
     * @param int[] $userIds
     * @param array<string, scalar|null> $data
     * @return array{ok: bool, status?: int, response?: array<string, mixed>, reason?: string, externalIds?: string[]}
     */
    public function sendToUserIds(array $userIds, string $title, string $message, array $data = []): array
    {
        if (!$this->oneSignalEnabled || trim($this->oneSignalAppId) === '' || trim($this->oneSignalApiKey) === '') {
            return ['ok' => false, 'reason' => 'onesignal_disabled_or_missing_config'];
        }

        $externalIds = [];
        foreach ($userIds as $userId) {
            $id = (int) $userId;
            if ($id > 0) {
                $externalIds[] = (string) $id;
            }
        }
        $externalIds = array_values(array_unique($externalIds));
        if ($externalIds === []) {
            return ['ok' => false, 'reason' => 'empty_external_ids'];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://onesignal.com/api/v1/notifications', [
                'headers' => [
                    'Authorization' => 'Basic '.$this->oneSignalApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'app_id' => $this->oneSignalAppId,
                    'include_external_user_ids' => $externalIds,
                    'channel_for_external_user_ids' => 'push',
                    'headings' => ['en' => $title, 'fr' => $title],
                    'contents' => ['en' => $message, 'fr' => $message],
                    'data' => $data,
                ],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            $payload = $response->toArray(false);

            $ok = $status >= 200 && $status < 300 && empty($payload['errors']);
            if (!$ok) {
                $this->logger->warning('OneSignal push rejected', [
                    'status' => $status,
                    'externalIds' => $externalIds,
                    'response' => $payload,
                ]);
            }

            return [
                'ok' => $ok,
                'status' => $status,
                'response' => $payload,
                'externalIds' => $externalIds,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('OneSignal push send failed', [
                'userIds' => $externalIds,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => $e->getMessage(),
                'externalIds' => $externalIds,
            ];
        }
    }
}
