<?php

namespace App\Tests\Controller\Marketplace;

use Symfony\Component\HttpFoundation\Request;

final class SalesDashboardControllerFunctionalTest extends MarketplaceFunctionalTestCase
{
    public function testSalesDashboardRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/statistiques-ventes');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/login');
    }
}

