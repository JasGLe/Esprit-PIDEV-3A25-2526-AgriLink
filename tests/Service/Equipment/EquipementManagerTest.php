<?php

namespace App\Tests\Service\Equipment;

use App\Entity\Equipement;
use App\Service\Equipment\EquipementManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour EquipementManager::validate().
 *
 * Chaque test cible une règle métier précise définie dans le service.
 * Aucune base de données n'est sollicitée : les Equipement sont instanciés
 * directement via les setters de l'entité.
 */
class EquipementManagerTest extends TestCase
{
    private EquipementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new EquipementManager();
    }

    /**
     * Vérifie qu'un équipement valide passe toutes les règles métier.
     * Cas nominal : toutes les valeurs sont dans les bornes acceptées.
     */
    public function testEquipementValide(): void
    {
        $equipement = new Equipement();
        $equipement->setNom('Tracteur John Deere');
        $equipement->setMarque('John Deere');
        $equipement->setSeuilJoursMaintenance(365);
        $equipement->setDateAcquisition(new \DateTime('-1 year'));

        $this->assertTrue($this->manager->validate($equipement));
    }

    /**
     * Le nom vide doit être rejeté.
     * Règle 1 : le nom est obligatoire (longueur minimale de 2 caractères).
     */
    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('');

        $this->manager->validate($equipement);
    }

    /**
     * Le nom doit contenir au moins 2 caractères.
     * Règle 1 : un nom d'un seul caractère n'identifie pas l'équipement de façon significative.
     */
    public function testNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('A');

        $this->manager->validate($equipement);
    }

    /**
     * Le nom ne peut pas dépasser 100 caractères.
     * Règle 2 : aligné sur la colonne SQL VARCHAR(100) de l'entité.
     */
    public function testNomTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom(str_repeat('A', 101));

        $this->manager->validate($equipement);
    }

    /**
     * La marque ne peut pas dépasser 50 caractères.
     * Règle 3 : aligné sur la colonne SQL VARCHAR(50) ; la marque reste optionnelle.
     */
    public function testMarqueTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('Tracteur valide');
        $equipement->setMarque(str_repeat('B', 51));

        $this->manager->validate($equipement);
    }

    /**
     * Le seuil en jours doit être strictement positif (> 0).
     * Règle 4 : un seuil de 0 jour rendrait l'alerte de maintenance immédiatement déclenchée.
     */
    public function testSeuilJoursNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('Tracteur valide');
        $equipement->setSeuilJoursMaintenance(0);

        $this->manager->validate($equipement);
    }

    /**
     * Le seuil négatif doit être rejeté.
     * Règle 4 : un seuil négatif n'a aucun sens fonctionnel pour une périodicité de maintenance.
     */
    public function testSeuilJoursNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('Tracteur valide');
        $equipement->setSeuilJoursMaintenance(-5);

        $this->manager->validate($equipement);
    }

    /**
     * Une date d'acquisition dans le futur doit être rejetée.
     * Règle 5 : un équipement ne peut pas avoir été acquis à une date non encore survenue.
     */
    public function testDateAcquisitionFutur(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $equipement = new Equipement();
        $equipement->setNom('Tracteur valide');
        $equipement->setDateAcquisition(new \DateTime('+1 day'));

        $this->manager->validate($equipement);
    }

    /**
     * isVehicule() doit retourner true pour un équipement de catégorie "Véhicule Motorisé".
     * Vérifie la logique métier interne de l'entité : les champs kilométrage/heures
     * sont actifs uniquement pour cette catégorie.
     */
    public function testIsVehiculeTrue(): void
    {
        $equipement = new Equipement();
        $equipement->setCategorie('Véhicule Motorisé');

        $this->assertTrue($equipement->isVehicule());
    }

    /**
     * isVehicule() doit retourner false pour un équipement de catégorie "Autre Équipement".
     * Vérifie que les champs véhicule ne sont pas activés pour les équipements non motorisés.
     */
    public function testIsVehiculeFalse(): void
    {
        $equipement = new Equipement();
        $equipement->setCategorie('Autre Équipement');

        $this->assertFalse($equipement->isVehicule());
    }
}