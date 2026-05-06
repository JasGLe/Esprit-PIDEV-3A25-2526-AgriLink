<?php
namespace App\Tests\Service;

use App\Entity\Exploitation\Culture;
use App\Service\CultureManager;
use PHPUnit\Framework\TestCase;

class CultureManagerTest extends TestCase
{
    private CultureManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CultureManager();
    }

    // ═══════════════════════════════════════════════════════
    // RÈGLE 1 — Nom obligatoire
    // ═══════════════════════════════════════════════════════

    /**
     *  Test acceptation : culture avec nom valide
     */
    public function testCultureAvecNomValide(): void
    {
        $culture = new Culture();
        $culture->setNom('Tomate');
        $culture->setStatut('PLANIFIEE');

        $this->assertTrue($this->manager->validate($culture));
    }

    /**
     *  Test refus : culture sans nom → exception
     */
    public function testCultureSansNom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la culture est obligatoire.');

        $culture = new Culture();
        $culture->setNom('');
        $culture->setStatut('PLANIFIEE');

        $this->manager->validate($culture);
    }

    /**
     *  Test refus : nom avec espaces uniquement → exception
     */
    public function testCultureNomEspacesUniquement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la culture est obligatoire.');

        $culture = new Culture();
        $culture->setNom('   ');

        $this->manager->validate($culture);
    }

    // ═══════════════════════════════════════════════════════
    // RÈGLE 2 — dateRecolte > dateSemis
    // ═══════════════════════════════════════════════════════

    /**
     *  Test acceptation : date récolte après date semis
     */
    public function testDatesValides(): void
    {
        $culture = new Culture();
        $culture->setNom('Blé');
        $culture->setStatut('PLANIFIEE');
        $culture->setDateSemis(new \DateTime('2026-01-01'));
        $culture->setDateRecolte(new \DateTime('2026-06-01'));

        $this->assertTrue($this->manager->validate($culture));
    }

    /**
     *  Test refus : date récolte avant date semis → exception
     */
    public function testDateRecolteAvantDateSemis(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'La date de récolte doit être postérieure à la date de semis.'
        );

        $culture = new Culture();
        $culture->setNom('Maïs');
        $culture->setDateSemis(new \DateTime('2026-06-01'));
        $culture->setDateRecolte(new \DateTime('2026-01-01')); // avant semis

        $this->manager->validate($culture);
    }

    /**
     *  Test refus : date récolte égale à date semis → exception
     */
    public function testDateRecolteEgaleADateSemis(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'La date de récolte doit être postérieure à la date de semis.'
        );

        $culture = new Culture();
        $culture->setNom('Piment');
        $culture->setDateSemis(new \DateTime('2026-03-01'));
        $culture->setDateRecolte(new \DateTime('2026-03-01')); 

        $this->manager->validate($culture);
    }

    /**
     *  Test acceptation : dates nulles → pas d'erreur sur les dates
     */
    public function testSansDates(): void
    {
        $culture = new Culture();
        $culture->setNom('Olivier');
        $culture->setStatut('PLANIFIEE');

        
        $this->assertTrue($this->manager->validate($culture));
    }

    // ═══════════════════════════════════════════════════════
    // RÈGLE 3 — Statut valide
    // ═══════════════════════════════════════════════════════

    /**
     *  Test acceptation : statut PLANIFIEE valide
     */
    public function testStatutValide(): void
    {
        $culture = new Culture();
        $culture->setNom('Carotte');
        $culture->setStatut('PLANIFIEE');

        $this->assertTrue($this->manager->validate($culture));
    }

 
    /**
     *  Test refus : statut inconnu → exception
     */
    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide');

        $culture = new Culture();
        $culture->setNom('Courgette');
        $culture->setStatut('STATUT_INEXISTANT');

        $this->manager->validate($culture);
    }

}