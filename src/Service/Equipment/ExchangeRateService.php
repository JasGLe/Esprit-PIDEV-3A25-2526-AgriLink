<?php

namespace App\Service\Equipment;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ExchangeRateService
 * ─────────────────────────────────────────────────────────────────────────────
 * Récupère les taux de change en temps réel TND → devises étrangères.
 *
 * Utilise l'API ExchangeRate-API (v6) avec TND comme devise de base.
 * Les taux sont mis en cache Symfony pendant 1 heure pour éviter de
 * dépasser le quota de l'API gratuite (1 500 requêtes/mois).
 *
 * Devises exposées (6) : USD, EUR, AED, SAR, GBP, KWD
 * Choisies car ce sont les devises les plus pertinentes pour les
 * agriculteurs tunisiens (échanges commerciaux avec UE, Golfe, UK).
 *
 * Comportement en cas d'erreur :
 *  - API indisponible / quota dépassé : retourne [] silencieusement.
 *  - La clé API invalide : retourne [] avec cache court (5 min).
 *  - Erreur réseau : retourne [] silencieusement.
 *  - Les templates masquent le widget/dropdown si rates = [].
 *
 * Paramétrage :
 *  - EXCHANGE_RATE_API_KEY : clé API (env var, injectée via services.yaml)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ExchangeRateService
{
    /**
     * Endpoint ExchangeRate-API v6 (base TND).
     * %s est remplacé par la clé API au moment de la requête.
     */
    private const API_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/TND';

    /** Durée de cache normale : 1 heure (3600 secondes). */
    private const CACHE_TTL = 3600;

    /**
     * Durée de cache courte utilisée en cas d'erreur API.
     * Évite de spammer l'API sur chaque page vue pendant une indisponibilité.
     */
    private const CACHE_TTL_ERROR = 300; // 5 minutes

    /** Clé de cache Symfony pour ce jeu de taux TND. */
    private const CACHE_KEY = 'exchangerate_from_tnd_v1';

    /**
     * Devises cibles avec leur drapeau emoji et nom en français.
     * Format : 'CODE' => ['🏳 emoji drapeau', 'Nom complet en français']
     */
    private const TARGET_CURRENCIES = [
        'USD' => ['🇺🇸', 'Dollar américain'],
        'EUR' => ['🇪🇺', 'Euro'],
        'AED' => ['🇦🇪', 'Dirham émirati'],
        'SAR' => ['🇸🇦', 'Riyal saoudien'],
        'GBP' => ['🇬🇧', 'Livre sterling'],
        'KWD' => ['🇰🇼', 'Dinar koweïtien'],
    ];

    /**
     * @param HttpClientInterface $httpClient         Client HTTP Symfony pour appeler ExchangeRate-API
     * @param CacheInterface      $cache              Cache Symfony (filesystem ou Redis selon config)
     * @param string              $exchangeRateApiKey Clé API ExchangeRate-API (depuis env var)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface      $cache,
        private readonly string              $exchangeRateApiKey
    ) {}

    /**
     * Retourne les taux de change TND → 6 devises cibles.
     *
     * Structure de retour :
     * [
     *   'USD' => ['rate' => 0.3235, 'flag' => '🇺🇸', 'name' => 'Dollar américain'],
     *   'EUR' => ['rate' => 0.2987, 'flag' => '🇪🇺', 'name' => 'Euro'],
     *   'AED' => ['rate' => 1.1879, 'flag' => '🇦🇪', 'name' => 'Dirham émirati'],
     *   'SAR' => ['rate' => 1.2131, 'flag' => '🇸🇦', 'name' => 'Riyal saoudien'],
     *   'GBP' => ['rate' => 0.2548, 'flag' => '🇬🇧', 'name' => 'Livre sterling'],
     *   'KWD' => ['rate' => 0.0994, 'flag' => '🇰🇼', 'name' => 'Dinar koweïtien'],
     * ]
     *
     * Calcul dans les templates :
     *  montant_converti = maintenance.cout * rates[code].rate
     *
     * Cache :
     *  - Succès : mis en cache 1 heure (CACHE_TTL)
     *  - Erreur API (status ≠ 200 ou result ≠ 'success') : cache court 5 min (CACHE_TTL_ERROR)
     *  - Exception réseau : non mis en cache, retourne []
     *
     * @return array<string, array{rate: float, flag: string, name: string}>
     *         Tableau vide si l'API est indisponible, la clé invalide ou le réseau inaccessible.
     */
    public function getRatesFromTND(): array
    {
        try {
            // ── Récupération avec cache (expire après CACHE_TTL secondes) ────
            return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                // Expiration normale : 1 heure
                $item->expiresAfter(self::CACHE_TTL);

                // ── Appel HTTP vers ExchangeRate-API ──────────────────────────
                $url      = sprintf(self::API_URL, $this->exchangeRateApiKey);
                $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);

                // ── Vérification du code HTTP ─────────────────────────────────
                if ($response->getStatusCode() !== 200) {
                    // Erreur API (quota dépassé, clé invalide...) → cache court + tableau vide
                    $item->expiresAfter(self::CACHE_TTL_ERROR);
                    return [];
                }

                $data = $response->toArray(false);

                // ── Vérification du champ "result" de l'API ───────────────────
                // L'API retourne {"result": "success", ...} ou {"result": "error", ...}
                if (($data['result'] ?? '') !== 'success') {
                    $item->expiresAfter(self::CACHE_TTL_ERROR);
                    return [];
                }

                // ── Extraction des taux pour les 6 devises cibles uniquement ──
                // On ne stocke pas tous les taux (170+ devises) pour économiser la mémoire cache
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
            // ── Erreur cache ou réseau : silencieux, templates masqueront les widgets ──
            return [];
        }
    }
}
