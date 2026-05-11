<?php

namespace App\Tests\Service;

use App\Service\OAuthService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OAuthServiceTest extends TestCase
{
    public function testHandleGoogleCallbackUsesGoogleCredentialsInTokenRequest(): void
    {
        $requests = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url, $options];

            if ($url === 'https://oauth2.googleapis.com/token') {
                return new MockResponse(json_encode([
                    'access_token' => 'google-access-token',
                ]));
            }

            return new MockResponse(json_encode([
                'sub' => 'google-user-123',
                'email' => 'user@example.com',
                'name' => 'Google User',
                'picture' => 'https://example.com/avatar.png',
            ]));
        });

        $service = new OAuthService(
            $httpClient,
            'google-client-id',
            'google-client-secret',
            'http://127.0.0.1:8000/oauth/google/callback',
        );

        $oauthUser = $service->handleGoogleCallback('auth-code-123');

        $this->assertSame([
            'provider' => 'google',
            'provider_id' => 'google-user-123',
            'email' => 'user@example.com',
            'name' => 'Google User',
            'picture' => 'https://example.com/avatar.png',
        ], $oauthUser);

        $this->assertCount(2, $requests);
        $this->assertSame('POST', $requests[0][0]);
        $this->assertSame('https://oauth2.googleapis.com/token', $requests[0][1]);
        parse_str($requests[0][2]['body'], $tokenRequestBody);
        $this->assertSame('google-client-id', $tokenRequestBody['client_id']);
        $this->assertSame('google-client-secret', $tokenRequestBody['client_secret']);
        $this->assertSame('auth-code-123', $tokenRequestBody['code']);
        $this->assertSame('authorization_code', $tokenRequestBody['grant_type']);
        $this->assertSame('http://127.0.0.1:8000/oauth/google/callback', $tokenRequestBody['redirect_uri']);
    }

    public function testHandleGoogleCallbackReturnsNullWhenTokenIsMissing(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'token_type' => 'Bearer',
            ])),
        ]);

        $service = new OAuthService(
            $httpClient,
            'google-client-id',
            'google-client-secret',
            'http://127.0.0.1:8000/oauth/google/callback',
        );

        $this->assertNull($service->handleGoogleCallback('auth-code-123'));
    }
}
