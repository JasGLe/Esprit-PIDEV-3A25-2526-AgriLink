<?php

namespace App\Tests\Fixtures;

use App\Entity\Activity\Activite;

/**
 * Fixture pour les tests - expose les setters protégés
 */
class ActiviteTestFixture extends Activite
{
    /**
     * Override de setDateDebut pour le rendre public dans les tests
     */
    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        return parent::setDateDebut($dateDebut);
    }

    /**
     * Override de setDateFin pour le rendre public dans les tests
     */
    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        return parent::setDateFin($dateFin);
    }
}
