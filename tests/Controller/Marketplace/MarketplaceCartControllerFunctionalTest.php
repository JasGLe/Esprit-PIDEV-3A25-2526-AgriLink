<?php

namespace App\Tests\Controller\Marketplace;

use Symfony\Component\HttpFoundation\Request;

final class MarketplaceCartControllerFunctionalTest extends MarketplaceFunctionalTestCase
{
    public function testPanierRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/marketplace/panier');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/login');
    }
}

