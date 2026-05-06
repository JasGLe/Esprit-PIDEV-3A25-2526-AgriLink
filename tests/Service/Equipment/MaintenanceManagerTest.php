<?php

namespace App\Tests\Service\Equipment;

use App\Entity\Maintenance;
use App\Service\Equipment\MaintenanceManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour MaintenanceManager::validate() et les helpers métier de Maintenance.
 *
 * Chaque test cible une règle métier précise définie dans le service ou l'entité.
 * Aucune base de données n'est sollicitée : les Maintenance sont instanciées
 * directement via les setters de l'entité (le constructeur initialise datePlanifiee à today).
 */
class MaintenanceManagerTest extends TestCase
{
    private MaintenanceManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MaintenanceManager();
    }

    /**
     * Vérifie qu'une maintenance valide passe toutes les règles métier.
     * Cas nominal : description suffisante, technicien court, coût positif, date future.
     */
    public function testMaintenanceValide(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange complète du moteur tracteur');
        $maintenance->setTechnicien('Ahmed Ben Ali');
        $maintenance->setCout('150.00');
        $maintenance->setDatePlanifiee(new \DateTime('+7 days'));

        $this->assertTrue($this->manager->validate($maintenance));
    }

    /**
     * La description vide doit être rejetée.
     * Règle 1 : la description est obligatoire (longueur minimale de 10 caractères).
     */
    public function testDescriptionObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription('');

        $this->manager->validate($maintenance);
    }

    /**
     * La description doit contenir au moins 10 caractères.
     * Règle 1 : une description trop courte n'est pas exploitable par le diagnostic IA.
     */
    public function testDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange'); // 7 caractères

        $this->manager->validate($maintenance);
    }

    /**
     * La description ne peut pas dépasser 1000 caractères.
     * Règle 2 : aligné sur la contrainte Assert\Length(max: 1000) de l'entité.
     */
    public function testDescriptionTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription(str_repeat('A', 1001));

        $this->manager->validate($maintenance);
    }

    /**
     * Le nom du technicien ne peut pas dépasser 100 caractères.
     * Règle 3 : aligné sur la colonne SQL VARCHAR(100) ; le champ est optionnel.
     */
    public function testTechnicienTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange complète du moteur tracteur');
        $maintenance->setTechnicien(str_repeat('B', 101));

        $this->manager->validate($maintenance);
    }

    /**
     * Un coût négatif doit être rejeté.
     * Règle 4 : on ne peut pas facturer un montant négatif pour une intervention.
     */
    public function testCoutNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange complète du moteur tracteur');
        $maintenance->setCout('-10.00');

        $this->manager->validate($maintenance);
    }

    /**
     * Un coût nul est valide (maintenance sans frais).
     * Règle 4 : 0.00 est la borne inférieure acceptée pour le coût.
     */
    public function testCoutNulAccepte(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange complète du moteur tracteur');
        $maintenance->setCout('0.00');

        $this->assertTrue($this->manager->validate($maintenance));
    }

    /**
     * Une date planifiée dans le passé doit être rejetée.
     * Règle 5 : créer une maintenance avec une date dépassée la mettrait immédiatement en retard.
     */
    public function testDatePlanifieePassee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $maintenance = new Maintenance();
        $maintenance->setDescription('Vidange complète du moteur tracteur');
        $maintenance->setDatePlanifiee(new \DateTime('-1 day'));

        $this->manager->validate($maintenance);
    }

    /**
     * Une maintenance terminée ne peut jamais être en retard.
     * Logique métier isEnRetard() : le statut "Terminée" court-circuite le calcul de retard,
     * même si la date planifiée est dans le passé.
     */
    public function testIsEnRetardFalseSiTerminee(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Terminée');
        $maintenance->setDatePlanifiee(new \DateTime('-5 days'));

        $this->assertFalse($maintenance->isEnRetard());
    }

    /**
     * Une maintenance annulée ne peut jamais être en retard.
     * Logique métier isEnRetard() : le statut "Annulée" court-circuite le calcul de retard,
     * même si la date planifiée est dans le passé.
     */
    public function testIsEnRetardFalseSiAnnulee(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Annulée');
        $maintenance->setDatePlanifiee(new \DateTime('-5 days'));

        $this->assertFalse($maintenance->isEnRetard());
    }

    /**
     * isTerminee() doit retourner true uniquement si statut = "Terminée".
     * Vérifie le helper métier de l'entité utilisé dans les vues pour les badges de statut.
     */
    public function testIsTermineeTrue(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Terminée');

        $this->assertTrue($maintenance->isTerminee());
    }

    /**
     * isTerminee() doit retourner false pour tout autre statut que "Terminée".
     * Vérifie que "Planifiée", "En cours" ou "Annulée" ne sont pas confondus avec "Terminée".
     */
    public function testIsTermineeFalse(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Planifiée');

        $this->assertFalse($maintenance->isTerminee());
    }
}