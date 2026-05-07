<?php

namespace App\Tests\Controller\usermanagement;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerFaceVerifyTest extends WebTestCase
{
    public function testVerifyFaceWithoutDescriptorReturnsBadRequest(): void
    {
        $client = static::createClient();

        // No descriptor in payload should be rejected by input validation.
        $client->request(
            'POST',
            '/api/face/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], JSON_THROW_ON_ERROR),
        );

        // Contract: endpoint returns a JSON 400 for malformed face verification requests.
        $this->assertResponseStatusCodeSame(400);
        $this->assertTrue(
            $client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'Expected JSON response'
        );
    }
}

