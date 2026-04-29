<?php

namespace App\Tests\Service\Activity;

use App\Entity\Activity\Evenement;
use App\Service\Activity\EvenementManager;
use App\Tests\ReflectionHelper;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    private EvenementManager $evenementManager;

    protected function setUp(): void
    {
        $this->evenementManager = new EvenementManager();
    }

    /**
     * Test : Événement valide avec tous les paramètres corrects
     */
    public function testValidEvenement(): void
    {
        $evenement = new Evenement();
        $evenement->setTitre('Conférence Agricole');
        $evenement->setDescription('Une conférence sur les nouvelles techniques agricoles');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle principale');

        $this->assertTrue($this->evenementManager->validate($evenement));
    }

    /**
     * Test : Titre vide doit lever une exception
     */
    public function testEvenementWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $evenement = new Evenement();
        $evenement->setTitre('');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Titre trop court (<3 caractères)
     */
    public function testEvenementWithTitleTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $evenement = new Evenement();
        $evenement->setTitre('AB');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Titre trop long (>100 caractères)
     */
    public function testEvenementWithTitleTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre ne peut pas dépasser 100 caractères');

        $evenement = new Evenement();
        $evenement->setTitre(str_repeat('a', 101));
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Date de l'événement dans le passé
     */
    public function testEvenementWithPastDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de l\'événement ne peut pas être dans le passé');

        $evenement = new Evenement();
        $evenement->setTitre('Événement passé');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('yesterday'));
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Date de l'événement obligatoire
     */
    public function testEvenementWithoutDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de l\'événement est obligatoire');

        $evenement = new Evenement();
        $evenement->setTitre('Événement sans date');
        $evenement->setTypeEvenement('OFFICIEL');
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Type invalide
     */
    public function testEvenementWithInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type doit être parmi');

        $evenement = new Evenement();
        $evenement->setTitre('Événement avec type invalide');
        $evenement->setTypeEvenement('INVALIDE');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Lieu vide
     */
    public function testEvenementWithoutLieu(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lieu est obligatoire');

        $evenement = new Evenement();
        $evenement->setTitre('Événement sans lieu');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('');

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Lieu trop long (>150 caractères)
     */
    public function testEvenementWithLieuTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lieu ne peut pas dépasser 150 caractères');

        $evenement = new Evenement();
        $evenement->setTitre('Événement avec lieu long');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu(str_repeat('a', 151));

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Description trop longue (>5000 caractères)
     */
    public function testEvenementWithDescriptionTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description ne peut pas dépasser 5000 caractères');

        $evenement = new Evenement();
        $evenement->setTitre('Événement avec description longue');
        $evenement->setTypeEvenement('OFFICIEL');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('tomorrow'));
        $evenement->setLieu('Salle');
        $evenement->setDescription(str_repeat('a', 5001));

        $this->evenementManager->validate($evenement);
    }

    /**
     * Test : Événement OFFICIEL
     */
    public function testIsOfficialEvenement(): void
    {
        $evenement = new Evenement();
        $evenement->setTypeEvenement('OFFICIEL');

        $this->assertTrue($this->evenementManager->isOfficial($evenement));
        $this->assertFalse($this->evenementManager->isPersonal($evenement));
    }

    /**
     * Test : Événement PERSONNEL
     */
    public function testIsPersonalEvenement(): void
    {
        $evenement = new Evenement();
        $evenement->setTypeEvenement('PERSONNEL');

        $this->assertTrue($this->evenementManager->isPersonal($evenement));
        $this->assertFalse($this->evenementManager->isOfficial($evenement));
    }

    /**
     * Test : Calculer le nombre de jours avant l'événement
     */
    public function testDaysUntilEvent(): void
    {
        $evenement = new Evenement();
        $eventDate = new \DateTime('+5 days');
        ReflectionHelper::setProperty($evenement, 'dateEvenement', $eventDate);

        $daysUntil = $this->evenementManager->daysUntilEvent($evenement);
        
        // Permet une petite marge (différence de quelques secondes)
        $this->assertGreaterThanOrEqual(4, $daysUntil);
        $this->assertLessThanOrEqual(5, $daysUntil);
    }

    /**
     * Test : Date de l'événement aujourd'hui (pas dans le passé)
     */
    public function testEvenementWithTodayDate(): void
    {
        // Créer un événement pour aujourd'hui à 14h (après maintenant)
        $today = new \DateTime('today 14:00:00');
        
        if ($today > new \DateTime()) {
            $evenement = new Evenement();
            $evenement->setTitre('Événement aujourd\'hui');
            $evenement->setTypeEvenement('OFFICIEL');
            ReflectionHelper::setProperty($evenement, 'dateEvenement', $today);
            $evenement->setLieu('Salle');

            // Ne doit pas lever d'exception
            $this->assertTrue($this->evenementManager->validate($evenement));
        } else {
            // Si aujourd'hui 14h est passé, on teste avec demain
            $evenement = new Evenement();
            $evenement->setTitre('Événement demain');
            $evenement->setTypeEvenement('OFFICIEL');
            ReflectionHelper::setProperty($evenement, 'dateEvenement', new \DateTime('+1 day'));
            $evenement->setLieu('Salle');

            $this->assertTrue($this->evenementManager->validate($evenement));
        }
    }
}
