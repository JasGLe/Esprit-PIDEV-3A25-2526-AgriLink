<?php

namespace App\Tests\Controller\usermanagement;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasswordResetControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
    }

    public function testForgotPasswordPageLoads(): void
    {
        // Boot an HTTP client against the Symfony test kernel.
        $client = static::createClient();

        // Smoke test: the forgot-password form route should render without crashing.
        $client->request('GET', '/forgot-password');

        $this->assertResponseIsSuccessful();
    }

    public function testPasswordResetSentPageLoads(): void
    {
        $client = static::createClient();

        // This confirmation page is shown after submitting the forgot-password form.
        $client->request('GET', '/forgot-password/sent');

        $this->assertResponseIsSuccessful();
    }
}
