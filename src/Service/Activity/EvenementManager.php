<?php

namespace App\Service\Activity;

use App\Entity\Activity\Evenement;

class EvenementManager
{
    /**
     * Valide un événement selon les règles métier
     * 
     * Règles métier :
     * 1. Le titre est obligatoire (3-100 caractères)
     * 2. La date de l'événement ne peut pas être dans le passé
     * 3. Le type doit être valide (OFFICIEL, PERSONNEL)
     * 4. Le lieu est obligatoire et max 150 caractères
     * 5. La description ne peut pas dépasser 5000 caractères
     */
    public function validate(Evenement $evenement): bool
    {
        // Règle 1 : Titre obligatoire
        if (empty(trim($evenement->getTitre()))) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }
        
        $titreLength = strlen(trim($evenement->getTitre()));
        if ($titreLength < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }
        
        if ($titreLength > 100) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 100 caractères');
        }

        // Règle 2 : Date ne peut pas être dans le passé
        if ($evenement->getDateEvenement() === null) {
            throw new \InvalidArgumentException('La date de l\'événement est obligatoire');
        }

        $today = new \DateTime('today');
        if ($evenement->getDateEvenement() < $today) {
            throw new \InvalidArgumentException('La date de l\'événement ne peut pas être dans le passé');
        }

        // Règle 3 : Type valide
        $typesValides = ['OFFICIEL', 'PERSONNEL'];
        if (!in_array($evenement->getTypeEvenement(), $typesValides)) {
            throw new \InvalidArgumentException(
                'Le type doit être parmi : ' . implode(', ', $typesValides)
            );
        }

        // Règle 4 : Lieu obligatoire
        if (empty(trim($evenement->getLieu()))) {
            throw new \InvalidArgumentException('Le lieu est obligatoire');
        }

        $lieuLength = strlen(trim($evenement->getLieu()));
        if ($lieuLength > 150) {
            throw new \InvalidArgumentException('Le lieu ne peut pas dépasser 150 caractères');
        }

        // Règle 5 : Description max 5000 caractères
        if ($evenement->getDescription() !== null) {
            $descLength = strlen($evenement->getDescription());
            if ($descLength > 5000) {
                throw new \InvalidArgumentException('La description ne peut pas dépasser 5000 caractères');
            }
        }

        return true;
    }

    /**
     * Vérifie si c'est un événement officiel
     */
    public function isOfficial(Evenement $evenement): bool
    {
        return $evenement->getTypeEvenement() === 'OFFICIEL';
    }

    /**
     * Vérifie si c'est un événement personnel
     */
    public function isPersonal(Evenement $evenement): bool
    {
        return $evenement->getTypeEvenement() === 'PERSONNEL';
    }

    /**
     * Calcule le nombre de jours avant l'événement
     */
    public function daysUntilEvent(Evenement $evenement): int
    {
        $eventDate = $evenement->getDateEvenement();
        if (!$eventDate instanceof \DateTimeInterface) {
            return 0;
        }

        $today = new \DateTime('today');

        $interval = $today->diff($eventDate);

        return (int) $interval->format('%a');
    }
}
