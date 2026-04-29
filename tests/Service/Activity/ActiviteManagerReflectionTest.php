<?php

namespace App\Tests\Service\Activity;

use App\Entity\Activity\Activite;
use App\Service\Activity\ActiviteManager;
use App\Tests\ReflectionHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests ActiviteManager SANS Fixture (utilisant Reflection)
 */
class ActiviteManagerReflectionTest extends TestCase
{
    private ActiviteManager $activiteManager;

    protected function setUp(): void
    {
        $this->activiteManager = new ActiviteManager();
    }

    /**
     * Exemple: Définir dateDebut avec Reflection au lieu de fixture
     */
    public function testValidActiviteWithReflection(): void
    {
        $activite = new Activite();  // ✅ Entité originale, pas fixture!
        $activite->setTitre('Semis de tomates');
        $activite->setTypeActivite('SEMIS');
        
        // ✅ Utiliser Reflection pour setDateDebut protégé
        ReflectionHelper::setProperty($activite, 'dateDebut', new \DateTime('tomorrow'));
        ReflectionHelper::setProperty($activite, 'dateFin', new \DateTime('+2 days'));
        
        $activite->setStatut('PLANIFIEE');
        $activite->setCoutEstime('50.00');
        $activite->setIdAgriculteur(1);

        $this->assertTrue($this->activiteManager->validate($activite));
    }

    /**
     * Comparer : Fixture vs Reflection
     */
    public function testDifference(): void
    {
        // 🎯 AVEC FIXTURE (ancien)
        // $activite = new ActiviteTestFixture();
        // $activite->setDateDebut(new \DateTime('tomorrow'));

        // 🎯 AVEC REFLECTION (nouveau)
        $activite = new Activite();
        ReflectionHelper::setProperty($activite, 'dateDebut', new \DateTime('tomorrow'));
        
        $this->assertNotNull(ReflectionHelper::getProperty($activite, 'dateDebut'));
    }
}
