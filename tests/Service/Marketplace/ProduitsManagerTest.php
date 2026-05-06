<?php

namespace App\Tests\Service\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Service\Marketplace\ProduitsManager;
use PHPUnit\Framework\TestCase;

final class ProduitsManagerTest extends TestCase
{
    public function testValidProduit(): void
    {
        $p = (new Produits())
            ->setNom('Tomate')
            ->setCategorie('Légume · kg')
            ->setPrixUnitaire(3.5)
            ->setOrigine('test')
            ->setActive(true)
            ->setQuantite(10);

        $manager = new ProduitsManager();
        $this->assertTrue($manager->validate($p));
    }

    public function testProduitWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = (new Produits())
            ->setNom(' ')
            ->setCategorie('Légume · kg')
            ->setPrixUnitaire(3.5)
            ->setOrigine('test')
            ->setActive(true)
            ->setQuantite(10);

        (new ProduitsManager())->validate($p);
    }

    public function testProduitWithInvalidPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = (new Produits())
            ->setNom('Tomate')
            ->setCategorie('Légume · kg')
            ->setPrixUnitaire(0)
            ->setOrigine('test')
            ->setActive(true)
            ->setQuantite(10);

        (new ProduitsManager())->validate($p);
    }

    public function testPromoActiveRequiresCodeAndDatesAndDiscountRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = (new Produits())
            ->setNom('Tomate')
            ->setCategorie('Légume · kg')
            ->setPrixUnitaire(3.5)
            ->setOrigine('test')
            ->setActive(true)
            ->setQuantite(10)
            ->setPromoActive(true)
            ->setPromoCode(null)
            ->setPromoDiscountPercent(10)
            ->setPromoStartAt(new \DateTimeImmutable('2026-05-10'))
            ->setPromoEndAt(new \DateTimeImmutable('2026-05-12'));

        (new ProduitsManager())->validate($p);
    }

    public function testRentalRequiresPositivePricePerDay(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = (new Produits())
            ->setNom('Tracteur')
            ->setCategorie('Équipement')
            ->setPrixUnitaire(1000)
            ->setOrigine('test')
            ->setActive(true)
            ->setQuantite(1)
            ->setIsRental(true)
            ->setRentalPricePerDay(0);

        (new ProduitsManager())->validate($p);
    }
}

