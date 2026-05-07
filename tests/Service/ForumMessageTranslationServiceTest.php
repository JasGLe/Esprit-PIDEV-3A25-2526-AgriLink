<?php

namespace App\Tests\Service;

use App\Service\ForumMessageTranslationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ForumMessageTranslationServiceTest extends TestCase
{
    public function testTranslateReturnsOriginalTextWhenTargetMatchesDetectedLanguage(): void
    {
        $httpClient = new MockHttpClient();
        $service = new ForumMessageTranslationService(
            $httpClient,
            'fake-api-key',
            'https://example.test',
            'gemini-test-model'
        );

        // "Bonjour..." is detected as French; target "fr" should short-circuit without API call.
        $result = $service->translate('Bonjour merci', 'fr');

        $this->assertSame('Bonjour merci', $result['translation']);
        $this->assertSame('fr', $result['target']);
        $this->assertSame('Francais', $result['targetLabel']);
        $this->assertSame('fr', $result['source']);
        $this->assertSame(0, $httpClient->getRequestsCount());
    }
}

