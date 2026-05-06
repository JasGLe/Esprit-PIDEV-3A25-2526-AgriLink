<?php

namespace App\Service\Activity;

use App\Entity\Activity\Activite;

class ActiviteManager
{
    /**
     * Valide une activité selon les règles métier
     * 
     * Règles métier :
     * 1. Le titre est obligatoire (3-100 caractères)
     * 2. La date de début ne peut pas être dans le passé
     * 3. La date de fin doit être >= à la date de début
     * 4. Le statut doit être valide (PLANIFIEE, EN_COURS, TERMINEE)
     * 5. Le coût estimé doit être positif ou zéro
     */
    public function validate(Activite $activite): bool
    {
        // Règle 1 : Titre obligatoire
        if (empty(trim($activite->getTitre()))) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }
        
        $titreLength = strlen(trim($activite->getTitre()));
        if ($titreLength < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }
        
        if ($titreLength > 100) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 100 caractères');
        }

        // Règle 2 : Date de début ne peut pas être dans le passé
        if ($activite->getDateDebut() === null) {
            throw new \InvalidArgumentException('La date de début est obligatoire');
        }

        $today = new \DateTime('today');
        if ($activite->getDateDebut() < $today) {
            throw new \InvalidArgumentException('La date de début ne peut pas être dans le passé');
        }

        // Règle 3 : Date de fin >= date de début
        if ($activite->getDateFin() !== null && $activite->getDateFin() < $activite->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin doit être >= à la date de début');
        }

        // Règle 4 : Statut valide
        $statusValides = ['PLANIFIEE', 'EN_COURS', 'TERMINEE'];
        if (!in_array($activite->getStatut(), $statusValides)) {
            throw new \InvalidArgumentException(
                'Le statut doit être parmi : ' . implode(', ', $statusValides)
            );
        }

        // Règle 5 : Coût estimé positif ou zéro
        if ($activite->getCoutEstime() !== null) {
            $cout = (float) $activite->getCoutEstime();
            if ($cout < 0) {
                throw new \InvalidArgumentException('Le coût estimé doit être positif ou zéro');
            }
        }

        return true;
    }

    /**
     * Vérifie si une activité peut être terminée
     */
    public function canComplete(Activite $activite): bool
    {
        return $activite->getStatut() !== 'TERMINEE';
    }

    /**
     * Marque une activité comme complétée
     */
    public function complete(Activite $activite): bool
    {
        if (!$this->canComplete($activite)) {
            throw new \InvalidArgumentException('Cette activité est déjà terminée');
        }

        $activite->setStatut('TERMINEE');
        return true;
    }
}
