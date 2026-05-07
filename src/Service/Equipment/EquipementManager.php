<?php

namespace App\Service\Equipment;

use App\Entity\Equipement;

/**
 * EquipementManager — service de validation des règles métier d'un équipement agricole.
 *
 * Ce service centralise la logique de validation qui ne peut pas être portée
 * uniquement par les contraintes Symfony Assert (ex : calculs dynamiques, règles croisées).
 * Il est appelé avant toute persistance d'un équipement en base de données.
 */
class EquipementManager
{
    /**
     * Valide un équipement selon les règles métier du module 6.
     *
     * Règles appliquées (dans l'ordre) :
     *   1. Nom obligatoire ≥ 2 caractères
     *   2. Nom ≤ 100 caractères
     *   3. Marque ≤ 50 caractères (si renseignée)
     *   4. Seuil jours de maintenance > 0 (si renseigné)
     *   5. Date d'acquisition ≤ aujourd'hui (si renseignée)
     *
     * @param Equipement $equipement L'équipement à valider
     * @return bool true si toutes les règles sont respectées
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function validate(Equipement $equipement): bool
    {
        // Règle 1 : Le nom est obligatoire et doit contenir au moins 2 caractères.
        // Un nom trop court (vide ou 1 caractère) ne permet pas d'identifier l'équipement
        // de façon significative dans les listes, le passeport ou le diagnostic IA.
        if (empty($equipement->getNom()) || strlen($equipement->getNom()) < 2) {
            throw new \InvalidArgumentException('Le nom est obligatoire et doit contenir au moins 2 caractères.');
        }

        // Règle 2 : Le nom ne dépasse pas 100 caractères.
        // Aligné sur la contrainte de longueur de la colonne SQL (VARCHAR 100)
        // et l'Assert\Length défini dans l'entité.
        if (strlen($equipement->getNom()) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères.');
        }

        // Règle 3 : La marque ne dépasse pas 50 caractères (si renseignée).
        // La marque est optionnelle ; si elle est fournie, elle doit respecter
        // la limite de la colonne SQL (VARCHAR 50) pour éviter une erreur Doctrine.
        if ($equipement->getMarque() !== null && strlen($equipement->getMarque()) > 50) {
            throw new \InvalidArgumentException('La marque ne peut pas dépasser 50 caractères.');
        }

        // Règle 4 : Le seuil jours de maintenance doit être > 0 (si renseigné).
        // Un seuil nul ou négatif n'a aucun sens fonctionnel : il rendrait toute
        // alerte de maintenance périodique immédiatement déclenchée ou impossible.
        if ($equipement->getSeuilJoursMaintenance() !== null && $equipement->getSeuilJoursMaintenance() <= 0) {
            throw new \InvalidArgumentException('Le seuil en jours doit être supérieur à 0.');
        }

        // Règle 5 : La date d'acquisition ne peut pas être dans le futur (si renseignée).
        // Un équipement ne peut pas avoir été acquis à une date qui n'est pas encore
        // survenue ; cela indiquerait une saisie erronée ou frauduleuse.
        if ($equipement->getDateAcquisition() !== null && $equipement->getDateAcquisition() > new \DateTime('today')) {
            throw new \InvalidArgumentException("La date d'acquisition ne peut pas être dans le futur.");
        }

        return true;
    }
}