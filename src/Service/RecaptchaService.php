<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const MIN_SCORE = 0.5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $recaptchaSecretKey
    ) {
    }

    public function verify(string $token, string $action): bool
    {
        if (empty($this->recaptchaSecretKey) || $this->recaptchaSecretKey === 'YOUR_RECAPTCHA_V3_SECRET_KEY') {
            return true;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->recaptchaSecretKey,
                    'response' => $token,
                ],
            ]);

            $data = $response->toArray();

            if (!($data['success'] ?? false)) {
                return false;
            }

            if (isset($data['action']) && $data['action'] !== $action) {
                return false;
            }

            if (isset($data['score']) && $data['score'] < self::MIN_SCORE) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }
}
