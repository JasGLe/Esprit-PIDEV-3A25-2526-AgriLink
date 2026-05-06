<?php

namespace App\Tests\Controller\forum;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ForumControllerTest extends WebTestCase
{
    public function testForumAiAssistantRejectsInvalidJsonPayload(): void
    {
        $client = static::createClient();

        // This endpoint validates JSON payload before any DB operation.
        $client->request('POST', '/forum/ai-assistant', [], [], ['CONTENT_TYPE' => 'application/json'], 'not-json');

        $this->assertResponseStatusCodeSame(400);
    }
}

