<?php

declare(strict_types=1);

namespace App\Marketplace;

/**
 * Les 24 gouvernorats (wilayas) de la Tunisie — libellés usuels en français.
 */
final class TunisiaRegionList
{
    /** @var list<string>|null */
    private static ?array $sorted = null;

    /**
     * @return list<string>
     */
    public static function getGovernorates(): array
    {
        if (self::$sorted !== null) {
            return self::$sorted;
        }

        $regions = [
            'Ariana',
            'Béja',
            'Ben Arous',
            'Bizerte',
            'Gabès',
            'Gafsa',
            'Jendouba',
            'Kairouan',
            'Kasserine',
            'Kébili',
            'Le Kef',
            'La Manouba',
            'Mahdia',
            'Médenine',
            'Monastir',
            'Nabeul',
            'Sfax',
            'Sidi Bouzid',
            'Siliana',
            'Sousse',
            'Tataouine',
            'Tozeur',
            'Tunis',
            'Zaghouan',
        ];

        natcasesort($regions);
        self::$sorted = array_values($regions);

        return self::$sorted;
    }
}
