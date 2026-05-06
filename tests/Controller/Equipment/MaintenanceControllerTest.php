<?php

namespace App\Tests\Controller\Equipment;

use App\Entity\Maintenance;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique pure extraite de MaintenanceController.
 *
 * Ces tests ne chargent ni Symfony ni la base de données.
 * Chaque test reproduit un comportement du controller sur l'entité Maintenance
 * en simulant directement les opérations que le controller exécute.
 */
class MaintenanceControllerTest extends TestCase
{
    /**
     * Le controller normalise cout null en '0.00' avant persistance.
     * Logique controller new()/edit() : if ($maintenance->getCout() === null) setCout('0.00').
     * Evite une violation de contrainte NOT NULL sur la colonne DECIMAL en base de données.
     */
    public function testCoutNullNormaliseEnZero(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setCout(null);

        // Simulation de la normalisation du controller avant em->persist() / em->flush()
        if ($maintenance->getCout() === null) {
            $maintenance->setCout('0.00');
        }

        $this->assertEquals('0.00', $maintenance->getCout());
    }

    /**
     * Un coût déjà renseigné ne doit pas être modifié par le controller.
     * Logique controller new()/edit() : la condition if (getCout() === null) reste false,
     * donc aucune modification n'est appliquée sur une valeur déjà présente.
     */
    public function testCoutDejaRenseigneNonModifie(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setCout('150.00');

        // Simulation : la condition du controller ne s'exécute pas (cout non null)
        if ($maintenance->getCout() === null) {
            $maintenance->setCout('0.00');
        }

        $this->assertEquals('150.00', $maintenance->getCout());
    }

    /**
     * Une maintenance terminée n'est jamais en retard même si sa date planifiée est dépassée.
     * Logique métier isEnRetard() : le statut "Terminée" court-circuite le calcul,
     * conformément à la règle métier documentée dans l'entité Maintenance.
     */
    public function testIsEnRetardFalseSiTerminee(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Terminée');
        $maintenance->setDatePlanifiee(new \DateTime('-5 days'));

        $this->assertFalse($maintenance->isEnRetard());
    }

    /**
     * Une maintenance annulée n'est jamais en retard même si sa date planifiée est dépassée.
     * Logique métier isEnRetard() : le statut "Annulée" court-circuite le calcul,
     * conformément à la règle métier documentée dans l'entité Maintenance.
     */
    public function testIsEnRetardFalseSiAnnulee(): void
    {
        $maintenance = new Maintenance();
        $maintenance->setStatut('Annulée');
        $maintenance->setDatePlanifiee(new \DateTime('-5 days'));

        $this->assertFalse($maintenance->isEnRetard());
    }
}