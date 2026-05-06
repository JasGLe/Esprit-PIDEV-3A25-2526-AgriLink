<?php

namespace App\Tests\Controller\Marketplace;

use Symfony\Component\HttpFoundation\Request;

final class MesCommandesControllerFunctionalTest extends MarketplaceFunctionalTestCase
{
    public function testMesCommandesRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/mes-commandes');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/login');
    }
}

