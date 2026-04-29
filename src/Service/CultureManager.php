<?php
namespace App\Service;

use App\Entity\Exploitation\Culture;

class CultureManager
{
    private const STATUTS_VALIDES = [
        'EN_ATTENTE',
        'PLANIFIEE',
        'EN_CROISSANCE',
        'RECOLTEE',
        'ABANDONNEE',
    ];

    /**
     * @throws \InvalidArgumentException 
     */
    public function validate(Culture $culture): bool
    {
        // Règle 1 — Nom obligatoire
        if (empty(trim((string) $culture->getNom()))) {
            throw new \InvalidArgumentException(
                'Le nom de la culture est obligatoire.'
            );
        }

        // Règle 2 — dateRecolte doit être après dateSemis
        if ($culture->getDateSemis() !== null
            && $culture->getDateRecolte() !== null
        ) {
            if ($culture->getDateRecolte() <= $culture->getDateSemis()) {
                throw new \InvalidArgumentException(
                    'La date de récolte doit être postérieure à la date de semis.'
                );
            }
        }

        // Règle 3 — Statut valide
        $statut = $culture->getStatut();
        if ($statut !== null) {
            $statutBase = explode('|', $statut)[0];
            if (!in_array($statutBase, self::STATUTS_VALIDES, true)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Statut invalide : "%s". Valeurs autorisées : %s.',
                        $statutBase,
                        implode(', ', self::STATUTS_VALIDES)
                    )
                );
            }
        }

        return true;
    }


}