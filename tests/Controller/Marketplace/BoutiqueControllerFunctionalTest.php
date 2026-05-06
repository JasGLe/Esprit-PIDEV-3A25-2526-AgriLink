<?php

namespace App\Tests\Controller\Marketplace;

use Symfony\Component\HttpFoundation\Request;

final class BoutiqueControllerFunctionalTest extends MarketplaceFunctionalTestCase
{
    public function testBoutiqueRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/boutique');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/login');
    }
}

