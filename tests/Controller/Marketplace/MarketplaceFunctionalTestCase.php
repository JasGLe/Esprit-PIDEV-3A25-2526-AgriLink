<?php

namespace App\Tests\Controller\Marketplace;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class MarketplaceFunctionalTestCase extends WebTestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureSchema();
    }

    protected static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $meta = $em->getMetadataFactory()->getAllMetadata();

        if ($meta !== []) {
            $tool = new SchemaTool($em);
            $tool->updateSchema($meta);
        }

        $em->clear();
        static::ensureKernelShutdown();
        self::$schemaReady = true;
    }
}

