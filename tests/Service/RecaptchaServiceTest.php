<?php

namespace App\Tests\Service;

use App\Service\RecaptchaService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RecaptchaServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private RecaptchaService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new RecaptchaService(
            $this->httpClient,
            'test-secret-key',
            $this->logger
        );
    }

    /**
     * Test successful reCAPTCHA v3 verification with good score
     */
    public function testVerifySuccessWithGoodScore(): void
    {
        $token = 'valid-token-123';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
            'challenge_ts' => '2026-04-13T10:25:48Z',
            'hostname' => 'localhost',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => 'test-secret-key',
                    'response' => $token,
                ],
            ])
            ->willReturn($response);

        $result = $this->service->verify($token, $action);

        $this->assertTrue($result);
    }

    /**
     * Test verification fails when Google returns success: false
     */
    public function testVerifyFailsWhenGoogleReturnsFailure(): void
    {
        $token = 'invalid-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('reCAPTCHA verification failed', $this->containsEqual(['invalid-input-response']));

        $result = $this->service->verify($token, $action);

        $this->assertFalse($result);
    }

    /**
     * Test verification fails when score is below minimum threshold (0.5)
     */
    public function testVerifyFailsWhenScoreTooLow(): void
    {
        $token = 'low-score-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.3,
            'action' => 'register',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('reCAPTCHA score too low', $this->anything());

        $result = $this->service->verify($token, $action);

        $this->assertFalse($result);
    }

    /**
     * Test verification with score exactly at minimum threshold (0.5)
     */
    public function testVerifySuccessWithMinimumScore(): void
    {
        $token = 'min-score-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.5,
            'action' => 'register',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->service->verify($token, $action);

        $this->assertTrue($result);
    }

    /**
     * Test verification fails when action does not match
     */
    public function testVerifyFailsWhenActionMismatch(): void
    {
        $token = 'valid-token';
        $expectedAction = 'register';
        $receivedAction = 'login';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.9,
            'action' => $receivedAction,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('reCAPTCHA action mismatch', [
                'expected' => $expectedAction,
                'received' => $receivedAction,
            ]);

        $result = $this->service->verify($token, $expectedAction);

        $this->assertFalse($result);
    }

    /**
     * Test verification returns false when token is empty
     */
    public function testVerifyFailsWithEmptyToken(): void
    {
        $this->httpClient->expects($this->never())
            ->method('request');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('reCAPTCHA: Empty token received');

        $result = $this->service->verify('', 'register');

        $this->assertFalse($result);
    }

    /**
     * Test verification returns true when secret key is not configured
     */
    public function testVerifySkipsWhenSecretKeyNotConfigured(): void
    {
        $service = new RecaptchaService(
            $this->httpClient,
            '',
            $this->logger
        );

        $this->httpClient->expects($this->never())
            ->method('request');

        $result = $service->verify('some-token', 'register');

        $this->assertTrue($result);
    }

    /**
     * Test verification returns true when secret key is default placeholder
     */
    public function testVerifySkipsWhenSecretKeyIsPlaceholder(): void
    {
        $service = new RecaptchaService(
            $this->httpClient,
            'YOUR_RECAPTCHA_V3_SECRET_KEY',
            $this->logger
        );

        $this->httpClient->expects($this->never())
            ->method('request');

        $result = $service->verify('some-token', 'register');

        $this->assertTrue($result);
    }

    /**
     * Test verification handles HTTP client exceptions gracefully
     */
    public function testVerifyHandlesHttpException(): void
    {
        $token = 'token';
        $action = 'register';

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('reCAPTCHA verification exception', $this->anything());

        $result = $this->service->verify($token, $action);

        $this->assertFalse($result);
    }

    /**
     * Test verification logs debug info on success
     */
    public function testVerifyLogsDebugInfoOnSuccess(): void
    {
        $token = 'valid-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.85,
            'action' => 'register',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->exactly(2))
            ->method('debug');

        $result = $this->service->verify($token, $action);

        $this->assertTrue($result);
    }

    /**
     * Test verification with multiple error codes
     */
    public function testVerifyFailsWithMultipleErrorCodes(): void
    {
        $token = 'invalid-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => false,
            'error-codes' => ['invalid-input-response', 'invalid-keys'],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('warning');

        $result = $this->service->verify($token, $action);

        $this->assertFalse($result);
    }

    /**
     * Test verification with high score (good human behavior)
     */
    public function testVerifySuccessWithHighScore(): void
    {
        $token = 'human-like-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.99,
            'action' => 'register',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->service->verify($token, $action);

        $this->assertTrue($result);
    }

    /**
     * Test verification with response missing optional action field
     */
    public function testVerifySuccessWithMissingActionField(): void
    {
        $token = 'valid-token';
        $action = 'register';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'score' => 0.9,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->service->verify($token, $action);

        $this->assertTrue($result);
    }
}
