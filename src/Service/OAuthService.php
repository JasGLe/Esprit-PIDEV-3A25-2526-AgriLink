<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuthService
{
    private string $googleClientId;
    private string $googleClientSecret;
    private string $googleRedirectUri;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $googleClientId,
        string $googleClientSecret,
        string $googleRedirectUri
    ) {
        $this->googleClientId = $googleClientId;
        $this->googleClientSecret = $googleClientSecret;
        $this->googleRedirectUri = $googleRedirectUri;
    }

    /**
     * Get Google OAuth authorization URL
     */
    public function getGoogleAuthUrl(): string
    {
        $params = [
            'client_id' => $this->googleClientId,
            'redirect_uri' => $this->googleRedirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'prompt' => 'consent',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange Google authorization code for access token and user info
     *
     * @return array{provider:'google',provider_id:string|null,email:string|null,name:string|null,picture:string|null}|null
     */
    public function handleGoogleCallback(string $code): ?array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'client_id' => $this->googleClientId,
                    'client_secret' => $this->googleClientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->googleRedirectUri,
                ],
            ]);

            $tokenData = $response->toArray();

            if (!isset($tokenData['access_token'])) {
                return null;
            }

            // Get user info
            $userResponse = $this->httpClient->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                ],
            ]);

            $userData = $userResponse->toArray();

            return [
                'provider' => 'google',
                'provider_id' => $userData['sub'] ?? null,
                'email' => $userData['email'] ?? null,
                'name' => $userData['name'] ?? null,
                'picture' => $userData['picture'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

}
