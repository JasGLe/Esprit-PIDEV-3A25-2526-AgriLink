<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuthService
{
    private HttpClientInterface $httpClient;
    private string $googleClientId;
    private string $googleClientSecret;
    private string $googleRedirectUri;
    private string $facebookAppId;
    private string $facebookAppSecret;
    private string $facebookRedirectUri;

    public function __construct(
        string $googleClientId,
        string $googleClientSecret,
        string $googleRedirectUri,
        string $facebookAppId,
        string $facebookAppSecret,
        string $facebookRedirectUri
    ) {
        $this->httpClient = HttpClient::create();
        $this->googleClientId = $googleClientId;
        $this->googleClientSecret = $googleClientSecret;
        $this->googleRedirectUri = $googleRedirectUri;
        $this->facebookAppId = $facebookAppId;
        $this->facebookAppSecret = $facebookAppSecret;
        $this->facebookRedirectUri = $facebookRedirectUri;
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
     * Get Facebook OAuth authorization URL
     */
    public function getFacebookAuthUrl(): string
    {
        $params = [
            'client_id' => $this->facebookAppId,
            'redirect_uri' => $this->facebookRedirectUri,
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'auth_type' => 'rerequest',
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    /**
     * Exchange Google authorization code for access token and user info
     */
    public function handleGoogleCallback(string $code): ?array
    {
        try {
            // Exchange code for access token
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'json' => [
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
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Exchange Facebook authorization code for access token and user info
     */
    public function handleFacebookCallback(string $code): ?array
    {
        try {
            // Exchange code for access token
            $response = $this->httpClient->request('GET', 'https://graph.facebook.com/v18.0/oauth/access_token', [
                'query' => [
                    'client_id' => $this->facebookAppId,
                    'client_secret' => $this->facebookAppSecret,
                    'code' => $code,
                    'redirect_uri' => $this->facebookRedirectUri,
                ],
            ]);

            $tokenData = $response->toArray();

            if (!isset($tokenData['access_token'])) {
                return null;
            }

            // Get user info
            $userResponse = $this->httpClient->request('GET', 'https://graph.facebook.com/me', [
                'query' => [
                    'access_token' => $tokenData['access_token'],
                    'fields' => 'id,name,email,picture.width(200).height(200)',
                ],
            ]);

            $userData = $userResponse->toArray();

            return [
                'provider' => 'facebook',
                'provider_id' => $userData['id'] ?? null,
                'email' => $userData['email'] ?? null,
                'name' => $userData['name'] ?? null,
                'picture' => $userData['picture']['data']['url'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
