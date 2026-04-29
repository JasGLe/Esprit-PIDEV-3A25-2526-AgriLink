<?php

namespace App\Tests\Service\Marketplace\Sales;

use App\Entity\Marketplace\Commandes;
use App\Service\Marketplace\Sales\OrderStatusFormatter;
use PHPUnit\Framework\TestCase;

final class OrderStatusFormatterTest extends TestCase
{
    public function testFormatOrderRefUsesIdWhenAvailable(): void
    {
        $commande = new Commandes();

        // Set private id using reflection (unit test only).
        $r = new \ReflectionClass($commande);
        $p = $r->getProperty('id');
        $p->setAccessible(true);
        $p->setValue($commande, 7);

        $formatter = new OrderStatusFormatter();
        $this->assertSame('#CMD007', $formatter->formatOrderRef($commande));
    }

    public function testHumanizeStatusNormalizesUnderscoresAndCase(): void
    {
        $formatter = new OrderStatusFormatter();

        $this->assertSame('En attente paiement cb', $formatter->humanizeStatus('EN_ATTENTE_PAIEMENT_CB'));
        $this->assertSame('-', $formatter->humanizeStatus(''));
        $this->assertSame('Livree', $formatter->humanizeStatus('LIVREE'));
    }
}

