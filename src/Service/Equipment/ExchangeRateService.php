<?php

namespace App\Service\Equipment;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ExchangeRateService
 * ─────────────────────────────────────────────────────────────────────────────
 * Récupère les taux de change en temps réel depuis ExchangeRate-API
 * (base TND → USD, EUR, AED, SAR, GBP, KWD).
 *
 * Le résultat est mis en cache Symfony pendant 1 heure pour éviter
 * de spammer l'API à chaque page vue.
 *
 * En cas d'erreur API ou réseau : retourne un tableau vide silencieusement.
 * La clé API n'est jamais exposée côté client.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ExchangeRateService
{
    // ── Endpoint ExchangeRate-API (v6, base TND) ───────────────────────────
    private const API_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/TND';

    // ── Durée de cache : 1 heure ───────────────────────────────────────────
    private const CACHE_TTL = 3600;

    // ── Clé de cache unique pour ce jeu de taux ───────────────────────────
    private const CACHE_KEY = 'exchangerate_from_tnd_v1';

    /**
     * Devises cibles exposées dans les templates.
     * Format : code => [flag emoji, nom complet en français]
     */
    private const TARGET_CURRENCIES = [
        'USD' => ['🇺🇸', 'Dollar américain'],
        'EUR' => ['🇪🇺', 'Euro'],
        'AED' => ['🇦🇪', 'Dirham émirati'],
        'SAR' => ['🇸🇦', 'Riyal saoudien'],
        'GBP' => ['🇬🇧', 'Livre sterling'],
        'KWD' => ['🇰🇼', 'Dinar koweïtien'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface      $cache,
        private readonly string              $exchangeRateApiKey
    ) {}

    /**
     * Retourne les taux de change TND → devises cibles.
     *
     * Structure de retour :
     * [
     *   'USD' => ['rate' => 0.3235, 'flag' => '🇺🇸', 'name' => 'Dollar américain'],
     *   'EUR' => ['rate' => 0.2987, 'flag' => '🇪🇺', 'name' => 'Euro'],
     *   …
     * ]
     *
     * @return array<string, array{rate: float, flag: string, name: string}>
     *         Tableau vide si l'API est indisponible ou la clé invalide.
     */
    public function getRatesFromTND(): array
    {
        try {
            // ── Récupération avec cache (1 heure) ──────────────────────────
            return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                // Expiration normale : 1 heure
                $item->expiresAfter(self::CACHE_TTL);

                // ── Appel HTTP vers ExchangeRate-API ───────────────────────
                $url      = sprintf(self::API_URL, $this->exchangeRateApiKey);
                $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);

                // ── Vérification du code HTTP ──────────────────────────────
                if ($response->getStatusCode() !== 200) {
                    // En cas d'erreur API → cache court (5 min) + tableau vide
                    $item->expiresAfter(300);
                    return [];
                }

                $data = $response->toArray(false);

                // ── Vérification du champ "result" retourné par l'API ──────
                if (($data['result'] ?? '') !== 'success') {
                    $item->expiresAfter(300);
                    return [];
                }

                // ── Extraction des taux pour les devises cibles uniquement ─
                $conversionRates = $data['conversion_rates'] ?? [];
                $result          = [];

                foreach (self::TARGET_CURRENCIES as $code => [$flag, $name]) {
                    if (isset($conversionRates[$code])) {
                        $result[$code] = [
                            'rate' => (float) $conversionRates[$code],
                            'flag' => $flag,
                            'name' => $name,
                        ];
                    }
                }

                return $result;
            });
        } catch (\Throwable $e) {
            // ── Si le cache ou l'API échoue : silencieux, pas d'erreur visible
            return [];
        }
    }
}
