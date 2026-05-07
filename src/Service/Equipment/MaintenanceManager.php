<?php

namespace App\Service\Equipment;

use App\Entity\Maintenance;

/**
 * MaintenanceManager — service de validation des règles métier d'une maintenance agricole.
 *
 * Ce service centralise la logique de validation programmatique qui complète
 * les contraintes Symfony Assert définies dans l'entité. Il est destiné à être
 * appelé dans le contrôleur avant toute persistance en base de données.
 */
class MaintenanceManager
{
    /**
     * Valide une maintenance selon les règles métier du module 6.
     *
     * Règles appliquées (dans l'ordre) :
     *   1. Description obligatoire ≥ 10 caractères
     *   2. Description ≤ 1000 caractères
     *   3. Technicien ≤ 100 caractères (si renseigné)
     *   4. Coût ≥ 0 (si renseigné)
     *   5. Date planifiée ≥ aujourd'hui (si renseignée)
     *
     * @param Maintenance $maintenance La maintenance à valider
     * @return bool true si toutes les règles sont respectées
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function validate(Maintenance $maintenance): bool
    {
        // Règle 1 : La description est obligatoire et doit contenir au moins 10 caractères.
        // Une description trop courte n'est pas exploitable par le service GroqDiagnosticService
        // pour générer un diagnostic IA pertinent sur l'opération de maintenance.
        if (empty($maintenance->getDescription()) || strlen($maintenance->getDescription()) < 10) {
            throw new \InvalidArgumentException('La description est obligatoire et doit contenir au moins 10 caractères.');
        }

        // Règle 2 : La description ne dépasse pas 1000 caractères.
        // Aligné sur la contrainte Assert\Length(max: 1000) de l'entité et la limite
        // raisonnable pour un champ de saisie dans l'interface utilisateur.
        if (strlen($maintenance->getDescription()) > 1000) {
            throw new \InvalidArgumentException('La description ne peut pas dépasser 1000 caractères.');
        }

        // Règle 3 : Le technicien ne dépasse pas 100 caractères (si renseigné).
        // Le champ est optionnel ; s'il est fourni, il doit respecter la colonne SQL
        // VARCHAR(100) pour éviter une erreur Doctrine lors de la persistance.
        if ($maintenance->getTechnicien() !== null && strlen($maintenance->getTechnicien()) > 100) {
            throw new \InvalidArgumentException('Le nom du technicien ne peut pas dépasser 100 caractères.');
        }

        // Règle 4 : Le coût doit être positif ou nul (si renseigné).
        // Un coût négatif n'a aucun sens métier : on ne peut pas facturer un montant
        // inférieur à zéro pour une opération de maintenance agricole.
        if ($maintenance->getCout() !== null && (float) $maintenance->getCout() < 0) {
            throw new \InvalidArgumentException('Le coût doit être positif ou nul.');
        }

        // Règle 5 : La date planifiée ne peut pas être dans le passé (si renseignée).
        // Créer une maintenance avec une date déjà dépassée la mettrait immédiatement
        // en statut "En retard" — l'utilisateur doit utiliser dateReelle pour les
        // interventions déjà effectuées.
        if ($maintenance->getDatePlanifiee() !== null && $maintenance->getDatePlanifiee() < new \DateTime('today')) {
            throw new \InvalidArgumentException('La date planifiée ne peut pas être dans le passé.');
        }

        return true;
    }
}