<?php

namespace App\Tests\Controller\usermanagement;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();

        // Basic smoke test for the login form endpoint.
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }
}

