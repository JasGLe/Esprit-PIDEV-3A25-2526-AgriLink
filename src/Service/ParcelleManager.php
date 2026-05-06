<?php
namespace App\Service;

use App\Entity\Exploitation\Parcelle;

class ParcelleManager
{
    private const TYPES_SOL_VALIDES = [
        'ARGILEUX',
        'SABLONNEUX',
        'LIMONEUX',
        'CAILLOUTEUX',
        'SALIN',
    ];

    private const ETATS_VALIDES = [
        'IRRIGUEE',
        'BOUR',
        'PARCOURS',
        'JACHERE',
        'FORESTIERE',
    ];

    /**
     * @throws \InvalidArgumentException
     */
    public function validate(Parcelle $parcelle): bool
    {
        if (empty(trim((string) $parcelle->getNom()))) {
            throw new \InvalidArgumentException('Le nom de la parcelle est obligatoire.');
        }

        $superficie = $parcelle->getSuperficie();
        if ($superficie !== null && $superficie <= 0) {
            throw new \InvalidArgumentException('La superficie doit être un nombre strictement positif.');
        }

        $typeSol = $parcelle->getTypeSol();
        if ($typeSol !== null && !in_array($typeSol, self::TYPES_SOL_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Type de sol invalide : "%s". Valeurs autorisées : %s.', $typeSol, implode(', ', self::TYPES_SOL_VALIDES))
            );
        }

        $etat = $parcelle->getEtat();
        if ($etat !== null && !in_array($etat, self::ETATS_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf('État invalide : "%s". Valeurs autorisées : %s.', $etat, implode(', ', self::ETATS_VALIDES))
            );
        }

        return true;
    }
}
