<?php

namespace App\Tests\Controller\usermanagement;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterChoicePageLoads(): void
    {
        $client = static::createClient();

        // The registration landing page lets users choose their account type.
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
    }
}

