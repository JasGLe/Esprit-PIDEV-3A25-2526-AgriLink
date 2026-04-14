<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const MIN_SCORE = 0.5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $recaptchaSecretKey,
        private LoggerInterface $logger
    ) {
    }

    public function verify(string $token, string $action): bool
    {
        if (empty($this->recaptchaSecretKey) || $this->recaptchaSecretKey === 'YOUR_RECAPTCHA_V3_SECRET_KEY') {
            return true;
        }

        if (empty($token)) {
            $this->logger->warning('reCAPTCHA: Empty token received');
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->recaptchaSecretKey,
                    'response' => $token,
                ],
            ]);

            $data = $response->toArray();

            $this->logger->debug('reCAPTCHA response', [
                'success' => $data['success'] ?? null,
                'action' => $data['action'] ?? null,
                'score' => $data['score'] ?? null,
                'error_codes' => $data['error-codes'] ?? null,
            ]);

            if (!($data['success'] ?? false)) {
                $this->logger->warning('reCAPTCHA verification failed', [
                    'error_codes' => $data['error-codes'] ?? [],
                ]);
                return false;
            }

            if (isset($data['action']) && $data['action'] !== $action) {
                $this->logger->warning('reCAPTCHA action mismatch', [
                    'expected' => $action,
                    'received' => $data['action'],
                ]);
                return false;
            }

            if (isset($data['score']) && $data['score'] < self::MIN_SCORE) {
                $this->logger->warning('reCAPTCHA score too low', [
                    'score' => $data['score'],
                    'min_score' => self::MIN_SCORE,
                ]);
                return false;
            }

            $this->logger->debug('reCAPTCHA verification successful', [
                'score' => $data['score'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('reCAPTCHA verification exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return false;
        }
    }
}
