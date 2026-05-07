<?php

namespace App\Tests\Service;

use App\Service\ForumAiAssistantException;
use App\Service\ForumAiAssistantService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Response;

final class ForumAiAssistantServiceTest extends TestCase
{
    public function testAskThrowsBadRequestForEmptyQuestion(): void
    {
        $service = new ForumAiAssistantService(
            new MockHttpClient(),
            'fake-groq-key',
            'https://example.test/v1',
            'llama-test-model'
        );

        // Guard clause should reject empty questions before any network operation.
        try {
            $service->ask('   ');
            $this->fail('Expected ForumAiAssistantException was not thrown.');
        } catch (ForumAiAssistantException $exception) {
            $this->assertSame('Question invalide.', $exception->getMessage());
            $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }
}

