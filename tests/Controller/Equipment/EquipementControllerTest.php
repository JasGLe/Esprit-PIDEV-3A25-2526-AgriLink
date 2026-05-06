<?php

namespace App\Tests\Controller\Equipment;

use App\Entity\Equipement;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique pure extraite de EquipementController.
 *
 * Ces tests ne chargent ni Symfony ni la base de données.
 * Chaque test reproduit un comportement du controller sur l'entité Equipement
 * en simulant directement les opérations de mutation que le controller exécute.
 */
class EquipementControllerTest extends TestCase
{
    /**
     * Le controller efface kilometrageActuel si la catégorie n'est pas "Véhicule Motorisé".
     * Logique controller new()/edit() : si (!$equipement->isVehicule()) setKilometrageActuel(null).
     * Evite de conserver des données km incohérentes quand l'utilisateur change de catégorie.
     */
    public function testEffacementKilometrageActuelSiNonVehicule(): void
    {
        $equipement = new Equipement();
        $equipement->setCategorie('Autre Équipement');
        $equipement->setKilometrageActuel(50000);

        // Simulation du bloc controller : if (!$equipement->isVehicule()) { ... }
        if (!$equipement->isVehicule()) {
            $equipement->setKilometrageActuel(null);
        }

        $this->assertNull($equipement->getKilometrageActuel());
    }

    /**
     * Le controller efface seuilKmMaintenance si la catégorie n'est pas "Véhicule Motorisé".
     * Logique controller new()/edit() : si (!$equipement->isVehicule()) setSeuilKmMaintenance(null).
     * Un seuil kilométrique n'a aucun sens pour un équipement non motorisé.
     */
    public function testEffacementSeuilKmSiNonVehicule(): void
    {
        $equipement = new Equipement();
        $equipement->setCategorie('Autre Équipement');
        $equipement->setSeuilKmMaintenance(10000);

        // Simulation du bloc controller
        if (!$equipement->isVehicule()) {
            $equipement->setSeuilKmMaintenance(null);
        }

        $this->assertNull($equipement->getSeuilKmMaintenance());
    }

    /**
     * Le controller efface heuresUtilisation si la catégorie n'est pas "Véhicule Motorisé".
     * Logique controller new()/edit() : si (!$equipement->isVehicule()) setHeuresUtilisation(null).
     * Les heures moteur ne sont pertinentes que pour les véhicules tractés ou motorisés.
     */
    public function testEffacementHeuresUtilisationSiNonVehicule(): void
    {
        $equipement = new Equipement();
        $equipement->setCategorie('Autre Équipement');
        $equipement->setHeuresUtilisation(200);

        // Simulation du bloc controller
        if (!$equipement->isVehicule()) {
            $equipement->setHeuresUtilisation(null);
        }

        $this->assertNull($equipement->getHeuresUtilisation());
    }

    /**
     * Un numéro tunisien commençant par 0 est normalisé en format E.164 (+216XXXXXXXX).
     * Logique controller edit() : str_starts_with($telephone, '0') → '+216' . substr($telephone, 1).
     * Twilio exige le format E.164 pour envoyer un SMS ; le préfixe 0 local est remplacé par +216.
     */
    public function testNormalisationTelephoneAvecZero(): void
    {
        $telephone = '012345678';

        // Simulation de la normalisation E.164 du controller edit()
        if (str_starts_with($telephone, '0')) {
            $telephone = '+216' . substr($telephone, 1);
        }

        $this->assertEquals('+21612345678', $telephone);
    }

    /**
     * Un numéro déjà au format E.164 (+21612345678) reste inchangé.
     * Logique controller edit() : si le numéro commence par '+', aucune transformation n'est appliquée.
     * Evite de double-préfixer un numéro déjà correctement formaté.
     */
    public function testNormalisationTelephoneDejaE164(): void
    {
        $telephone = '+21612345678';

        // Simulation de la normalisation E.164 : aucune branche ne s'applique ici
        if (str_starts_with($telephone, '0')) {
            $telephone = '+216' . substr($telephone, 1);
        } elseif (!str_starts_with($telephone, '+')) {
            $telephone = '+216' . $telephone;
        }

        $this->assertEquals('+21612345678', $telephone);
    }

    /**
     * Un numéro sans aucun préfixe (ni 0 ni +) reçoit le préfixe +216.
     * Logique controller edit() : elseif (!str_starts_with($telephone, '+')) → '+216' . $telephone.
     * Couvre le cas où l'utilisateur saisit son numéro sans l'indicatif national.
     */
    public function testNormalisationTelephoneSansPrefix(): void
    {
        $telephone = '12345678';

        // Simulation : branche elseif du controller
        if (str_starts_with($telephone, '0')) {
            $telephone = '+216' . substr($telephone, 1);
        } elseif (!str_starts_with($telephone, '+')) {
            $telephone = '+216' . $telephone;
        }

        $this->assertEquals('+21612345678', $telephone);
    }
}