<?php

namespace App\Tests\Service;

use App\Entity\Exploitation\Parcelle;
use App\Service\ParcelleManager;
use PHPUnit\Framework\TestCase;

class ParcelleManagerTest extends TestCase
{
    private ParcelleManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ParcelleManager();
    }


    /**
     * Test acceptation : parcelle avec nom valide
     */
    public function testParcelleAvecNomValide(): void
    {
        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Nord');
        $parcelle->setSuperficie(5.0);

        $this->assertTrue($this->manager->validate($parcelle));
    }

    /**
     * Test refus : parcelle sans nom 
     */
    public function testParcelleSansNom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la parcelle est obligatoire.');

        $parcelle = new Parcelle();
        $parcelle->setNom('');
        $parcelle->setSuperficie(5.0);

        $this->manager->validate($parcelle);
    }

    /**
     * Test refus : nom composé uniquement d'espaces 
     */
    public function testParcelleNomEspacesUniquement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la parcelle est obligatoire.');

        $parcelle = new Parcelle();
        $parcelle->setNom('   ');

        $this->manager->validate($parcelle);
    }


    /**
     * Test acceptation : superficie positive valide.
     */
    public function testSuperficiePositiveValide(): void
    {
        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Sud');
        $parcelle->setSuperficie(12.5);

        $this->assertTrue($this->manager->validate($parcelle));
    }

    /**
     * Test refus : superficie négative 
     */
    public function testSuperficieNegativeInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La superficie doit être un nombre strictement positif.');

        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Test');
        $parcelle->setSuperficie(-5.0);

        $this->manager->validate($parcelle);
    }


    /**
     * Test acceptation : type de sol 'ARGILEUX' valide.
     */
    public function testTypeSolArgileuxValide(): void
    {
        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Test');
        $parcelle->setTypeSol('ARGILEUX');

        $this->assertTrue($this->manager->validate($parcelle));
    }


    /**
     * Test refus : type de sol inconnu 
     */
    public function testTypeSolInconnu(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de sol invalide');

        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Test');
        $parcelle->setTypeSol('VOLCANIQUE');

        $this->manager->validate($parcelle);
    }

 
    /**
     * Test acceptation : état 'IRRIGUEE' valide.
     */
    public function testEtatIrrigueeValide(): void
    {
        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Test');
        $parcelle->setEtat('IRRIGUEE');

        $this->assertTrue($this->manager->validate($parcelle));
    }



    /**
     * Test refus : état inconnu → exception.
     */
    public function testEtatInconnu(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('État invalide');

        $parcelle = new Parcelle();
        $parcelle->setNom('Parcelle Test');
        $parcelle->setEtat('ABANDONNEE'); 

        $this->manager->validate($parcelle);
    }
}








