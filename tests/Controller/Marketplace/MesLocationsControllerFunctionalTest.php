<?php

namespace App\Tests\Controller\Marketplace;

use Symfony\Component\HttpFoundation\Request;

final class MesLocationsControllerFunctionalTest extends MarketplaceFunctionalTestCase
{
    public function testMesLocationsRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/mes-locations');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/login');
    }
}

