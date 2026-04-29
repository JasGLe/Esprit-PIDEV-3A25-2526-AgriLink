<?php

namespace App\Tests\Service\Activity;

use App\Tests\Fixtures\ActiviteTestFixture;
use App\Service\Activity\ActiviteManager;
use PHPUnit\Framework\TestCase;

class ActiviteManagerTest extends TestCase
{
    private ActiviteManager $activiteManager;

    protected function setUp(): void
    {
        $this->activiteManager = new ActiviteManager();
    }

    /**
     * Test : Activité valide avec tous les paramètres corrects
     */
    public function testValidActivite(): void
    {
        $activite = new ActiviteTestFixture();
        $activite->setTitre('Semis de tomates');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setDateFin(new \DateTime('+2 days'));
        $activite->setStatut('PLANIFIEE');
        $activite->setCoutEstime('50.00');
        $activite->setIdAgriculteur(1);

        $this->assertTrue($this->activiteManager->validate($activite));
    }

    /**
     * Test : Titre vide doit lever une exception
     */
    public function testActiviteWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Titre trop court (<3 caractères)
     */
    public function testActiviteWithTitleTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('AB');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Titre trop long (>100 caractères)
     */
    public function testActiviteWithTitleTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre ne peut pas dépasser 100 caractères');

        $activite = new ActiviteTestFixture();
        $activite->setTitre(str_repeat('a', 101));
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Date de début dans le passé
     */
    public function testActiviteWithPastDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de début ne peut pas être dans le passé');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité passée');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('yesterday'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Date de début obligatoire
     */
    public function testActiviteWithoutDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de début est obligatoire');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité sans date');
        $activite->setTypeActivite('SEMIS');
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Date de fin avant date de début
     */
    public function testActiviteWithDateFinBeforeDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être >= à la date de début');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité invalide');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('+5 days'));
        $activite->setDateFin(new \DateTime('+2 days'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Statut invalide
     */
    public function testActiviteWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être parmi');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité avec statut invalide');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('INVALIDE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Coût négatif
     */
    public function testActiviteWithNegativeCost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le coût estimé doit être positif ou zéro');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité avec coût négatif');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('PLANIFIEE');
        $activite->setCoutEstime('-10.00');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->validate($activite);
    }

    /**
     * Test : Marquer une activité comme terminée
     */
    public function testCompleteActivite(): void
    {
        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité à compléter');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('PLANIFIEE');
        $activite->setIdAgriculteur(1);

        $this->assertTrue($this->activiteManager->complete($activite));
        $this->assertEquals('TERMINEE', $activite->getStatut());
    }

    /**
     * Test : Ne pas pouvoir compléter une activité déjà terminée
     */
    public function testCompleteAlreadyCompletedActivite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cette activité est déjà terminée');

        $activite = new ActiviteTestFixture();
        $activite->setTitre('Activité déjà terminée');
        $activite->setTypeActivite('SEMIS');
        $activite->setDateDebut(new \DateTime('tomorrow'));
        $activite->setStatut('TERMINEE');
        $activite->setIdAgriculteur(1);

        $this->activiteManager->complete($activite);
    }

    /**
     * Test : Vérifier si une activité peut être complétée
     */
    public function testCanCompleteActivite(): void
    {
        $activite = new ActiviteTestFixture();
        $activite->setStatut('EN_COURS');

        $this->assertTrue($this->activiteManager->canComplete($activite));
    }

    /**
     * Test : Vérifier qu'on ne peut pas compléter une activité terminée
     */
    public function testCannotCompleteTerminatedActivite(): void
    {
        $activite = new ActiviteTestFixture();
        $activite->setStatut('TERMINEE');

        $this->assertFalse($this->activiteManager->canComplete($activite));
    }
}
