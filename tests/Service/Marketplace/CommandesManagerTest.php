<?php

namespace App\Tests\Service\Marketplace;

use App\Entity\Marketplace\Commandes;
use App\Service\Marketplace\CommandesManager;
use PHPUnit\Framework\TestCase;

final class CommandesManagerTest extends TestCase
{
    public function testValidCommande(): void
    {
        $c = (new Commandes())
            ->setNumCommande('MKT-20260506-ABC123')
            ->setDateCommande(new \DateTimeImmutable('2026-05-06'))
            ->setQuantite(3)
            ->setPrixTotal(120.5)
            ->setStatus('EN_PREPARATION')
            ->setEmail('buyer@test.com');

        $manager = new CommandesManager();
        $this->assertTrue($manager->validate($c));
    }

    public function testCommandeWithoutNumCommande(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $c = (new Commandes())
            ->setNumCommande(' ')
            ->setDateCommande(new \DateTimeImmutable('2026-05-06'))
            ->setQuantite(1)
            ->setPrixTotal(10)
            ->setStatus('EN_PREPARATION');

        (new CommandesManager())->validate($c);
    }

    public function testCommandeWithInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $c = (new Commandes())
            ->setNumCommande('MKT-20260506-ABC123')
            ->setDateCommande(new \DateTimeImmutable('2026-05-06'))
            ->setQuantite(1)
            ->setPrixTotal(10)
            ->setStatus('EN_PREPARATION')
            ->setEmail('invalid_email');

        (new CommandesManager())->validate($c);
    }

    public function testCommandeWithNegativePromoDiscountTotal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $c = (new Commandes())
            ->setNumCommande('MKT-20260506-ABC123')
            ->setDateCommande(new \DateTimeImmutable('2026-05-06'))
            ->setQuantite(1)
            ->setPrixTotal(10)
            ->setStatus('EN_PREPARATION')
            ->setPromoDiscountTotal(-1);

        (new CommandesManager())->validate($c);
    }
}

