<?php

namespace App\Tests\Fixtures;

use App\Entity\Activity\Evenement;

/**
 * Fixture pour les tests - expose les setters protégés
 */
class EvenementTestFixture extends Evenement
{
    /**
     * Override de setDateEvenement pour le rendre public dans les tests
     */
    public function setDateEvenement(?\DateTimeInterface $dateEvenement): static
    {
        return parent::setDateEvenement($dateEvenement);
    }
}
