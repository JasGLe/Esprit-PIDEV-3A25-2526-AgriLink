<?php

namespace App\Service;

use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\LigneCommandeRepository;
use App\Repository\Marketplace\MarketplaceUserEventRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class MarketplaceProductRecommendationService
{
    public const MODE_TOP_SELLING = 'top_selling';
    public const MODE_HISTORY = 'history';
    public const MODE_CATEGORY = 'category';
    public const MODE_HYBRID = 'hybrid';

    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly MarketplaceUserEventRepository $eventRepository,
        private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{
     *   recommended: list<Produits>,
     *   similar: list<Produits>,
     *   trending: list<Produits>,
     *   offers: list<Produits>
     * }
     */
    public function buildSectionsFor(?User $user, int $limitPerSection = 6): array
    {
        $limitPerSection = max(1, $limitPerSection);
        $catalog = $this->produitsRepository->findPublicMarketplaceCatalog(null, 'all', null, 'recent');
        if ($catalog === []) {
            return ['recommended' => [], 'similar' => [], 'trending' => [], 'offers' => []];
        }

        $globalSales = $this->ligneCommandeRepository->fetchGlobalTopSellingProductScores();
        $interactionScores = [];
        $searchTerms = [];
        $lastViewedProductId = null;

        if ($user instanceof User && $user->getId() !== null) {
            $uid = (int) $user->getId();
            $interactionScores = $this->eventRepository->fetchUserProductInteractionScores($uid);
            $searchTerms = $this->eventRepository->fetchRecentSearchQueries($uid, 25);
            $lastViewedProductId = $this->eventRepository->findLastViewedProductId($uid);
        }

        $catalogList = array_values($catalog);

        $rankedSections = $this->rankSectionsWithPython(
            $catalogList,
            $globalSales,
            $interactionScores,
            $searchTerms,
            $lastViewedProductId,
            $limitPerSection
        );

        $byId = [];
        foreach ($catalog as $p) {
            if ($p->getId() !== null) {
                $byId[(int) $p->getId()] = $p;
            }
        }

        $mapIdsToProducts = static function (array $ids) use ($byId, $limitPerSection): array {
            $out = [];
            foreach ($ids as $id) {
                $i = (int) $id;
                if ($i > 0 && isset($byId[$i])) {
                    $out[] = $byId[$i];
                }
                if (\count($out) >= $limitPerSection) {
                    break;
                }
            }

            return $out;
        };

        $recommended = $mapIdsToProducts($rankedSections['recommended'] ?? []);
        $similar = $mapIdsToProducts($rankedSections['similar'] ?? []);
        $trending = $mapIdsToProducts($rankedSections['trending'] ?? []);
        $offers = $mapIdsToProducts($rankedSections['offers'] ?? []);

        // Fallbacks if ML returns empty rails.
        if ($trending === []) {
            $trending = $mapIdsToProducts($this->rankInPhp($catalogList, $globalSales, [], [], self::MODE_TOP_SELLING, $limitPerSection));
        }
        if ($recommended === []) {
            $recommended = $mapIdsToProducts($this->rankInPhp($catalogList, $globalSales, $interactionScores, [], self::MODE_HYBRID, $limitPerSection));
        }
        if ($offers === []) {
            $offers = array_values(array_slice(array_filter($catalog, static fn (Produits $p): bool => $p->isPromoActive()), 0, $limitPerSection));
        }

        return [
            'recommended' => $recommended,
            'similar' => $similar,
            'trending' => $trending,
            'offers' => $offers,
        ];
    }

    /**
     * @return list<Produits>
     */
    public function recommendFor(?User $user, string $mode = self::MODE_HYBRID, int $limit = 6): array
    {
        $mode = $this->normalizeMode($mode);
        $limit = max(1, $limit);
        $catalog = $this->produitsRepository->findPublicMarketplaceCatalog(null, 'all', null, 'recent');
        if ($catalog === []) {
            return [];
        }

        $globalSales = $this->ligneCommandeRepository->fetchGlobalTopSellingProductScores();
        $userProductSales = [];
        $userCategorySales = [];

        $email = strtolower(trim((string) ($user?->getEmail() ?? '')));
        if ($email !== '') {
            $signals = $this->ligneCommandeRepository->fetchBuyerPurchaseSignalsByEmail($email);
            $userProductSales = $signals['productScores'];
            $userCategorySales = $signals['categoryScores'];
        }

        $hasGlobalSignals = $this->hasPositiveSignals($globalSales);
        $hasUserProductSignals = $this->hasPositiveSignals($userProductSales);
        $hasUserCategorySignals = $this->hasPositiveSignals($userCategorySales);
        $hasAnySignals = $hasGlobalSignals || $hasUserProductSignals || $hasUserCategorySignals;

        // Prevent fake/default ranking when no meaningful signals exist.
        if (
            ($mode === self::MODE_TOP_SELLING && !$hasGlobalSignals)
            || ($mode === self::MODE_HISTORY && !$hasUserProductSignals)
            || ($mode === self::MODE_CATEGORY && !$hasUserCategorySignals)
            || ($mode === self::MODE_HYBRID && !$hasAnySignals)
        ) {
            return [];
        }

        $rankedIds = $this->rankWithPython(
            array_values($catalog),
            $globalSales,
            $userProductSales,
            $userCategorySales,
            $mode,
            $limit
        );
        if ($rankedIds === []) {
            $rankedIds = $this->rankInPhp(
                array_values($catalog),
                $globalSales,
                $userProductSales,
                $userCategorySales,
                $mode,
                $limit
            );
        }

        $byId = [];
        foreach ($catalog as $p) {
            $byId[(int) $p->getId()] = $p;
        }

        $out = [];
        foreach ($rankedIds as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<Produits> $catalog
     * @param array<string, float> $globalSales
     * @param array<string, float> $userProductSales
     * @param array<string, float> $userCategorySales
     * @return list<int>
     */
    private function rankWithPython(
        array $catalog,
        array $globalSales,
        array $userProductSales,
        array $userCategorySales,
        string $mode,
        int $limit
    ): array {
        $script = $this->projectDir.'\\python\\recommend_marketplace.py';
        if (!is_file($script)) {
            return [];
        }

        $productsPayload = [];
        foreach ($catalog as $p) {
            $id = (int) $p->getId();
            if ($id <= 0) {
                continue;
            }
            $productsPayload[] = [
                'id' => $id,
                'name' => (string) $p->getNom(),
                'category' => strtoupper(trim((string) ($p->getCategory() ?? ''))),
                'price' => (float) $p->getPrixUnitaire(),
            ];
        }

        $payload = [
            'products' => $productsPayload,
            'global_sales' => $globalSales,
            'user_product_sales' => $userProductSales,
            'user_category_sales' => $userCategorySales,
            'mode' => $mode,
            'limit' => $limit,
        ];

        $commands = [
            ['python', $script],
            ['py', '-3', $script], // Windows fallback
        ];
        foreach ($commands as $cmd) {
            try {
                $process = new Process($cmd, $this->projectDir);
                $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $process->setInput($jsonPayload === false ? null : $jsonPayload);
                $process->setTimeout(3);
                $process->run();
                if (!$process->isSuccessful()) {
                    continue;
                }
                $json = json_decode($process->getOutput(), true);
                if (!\is_array($json) || !isset($json['ids']) || !\is_array($json['ids'])) {
                    continue;
                }

                $ids = [];
                foreach ($json['ids'] as $id) {
                    $i = (int) $id;
                    if ($i > 0) {
                        $ids[] = $i;
                    }
                }
                if ($ids !== []) {
                    return $ids;
                }
            } catch (\Throwable) {
                // Silent fallback to PHP ranking.
            }
        }

        return [];
    }

    /**
     * @param list<Produits> $catalog
     * @param array<string, float> $globalSales
     * @param array<string, float> $interactionScores
     * @param list<string> $searchTerms
     *
     * @return array{recommended: list<int>, similar: list<int>, trending: list<int>, offers: list<int>}
     */
    private function rankSectionsWithPython(
        array $catalog,
        array $globalSales,
        array $interactionScores,
        array $searchTerms,
        ?int $lastViewedProductId,
        int $limit
    ): array {
        $script = $this->projectDir.'\\python\\recommend_marketplace.py';
        if (!is_file($script)) {
            return ['recommended' => [], 'similar' => [], 'trending' => [], 'offers' => []];
        }

        $productsPayload = [];
        foreach ($catalog as $p) {
            $id = (int) ($p->getId() ?? 0);
            if ($id <= 0) {
                continue;
            }
            $productsPayload[] = [
                'id' => $id,
                'name' => (string) $p->getNom(),
                'description' => (string) ($p->getDescription() ?? ''),
                'category' => strtoupper(trim((string) ($p->getCategory() ?? ''))),
                'stock' => (int) $p->getQuantite(),
                'price' => (float) $p->getPrixUnitaire(),
                'promo_active' => (bool) $p->isPromoActive(),
            ];
        }

        $payload = [
            'products' => $productsPayload,
            'global_sales' => $globalSales,
            'user_interaction_scores' => $interactionScores,
            'search_terms' => $searchTerms,
            'last_viewed_product_id' => $lastViewedProductId,
            'limit' => $limit,
        ];

        $pythonBin = trim((string) (getenv('PYTHON_BIN') ?: ($_ENV['PYTHON_BIN'] ?? '')));
        $commands = array_values(array_filter([
            $pythonBin !== '' ? [$pythonBin, $script] : null,
            ['python', $script],
            ['py', '-3', $script],
        ]));
        foreach ($commands as $cmd) {
            try {
                $process = new Process($cmd, $this->projectDir);
                $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $process->setInput($jsonPayload === false ? null : $jsonPayload);
                $process->setTimeout(5);
                $process->run();

                // When Python errors, Symfony used to silently fallback to PHP ranking (ignores search terms).
                // Log stderr to help debug missing Python / missing sklearn / PATH issues in web runtime.
                if (!$process->isSuccessful()) {
                    $this->logger?->warning('Marketplace recommendation Python failed.', [
                        'cmd' => $cmd,
                        'exit_code' => $process->getExitCode(),
                        'error_output' => trim($process->getErrorOutput()),
                        'output' => trim($process->getOutput()),
                    ]);
                    continue;
                }
                $json = json_decode($process->getOutput(), true);
                if (!\is_array($json)) {
                    continue;
                }
                $sections = $json['sections'] ?? null;
                if (!\is_array($sections)) {
                    continue;
                }

                $out = ['recommended' => [], 'similar' => [], 'trending' => [], 'offers' => []];
                foreach ($out as $k => $_) {
                    $ids = $sections[$k] ?? [];
                    if (!\is_array($ids)) {
                        continue;
                    }
                    foreach ($ids as $id) {
                        $i = (int) $id;
                        if ($i > 0) {
                            $out[$k][] = $i;
                        }
                    }
                }
                return $out;
            } catch (\Throwable) {
                // silent fallback
            }
        }

        return ['recommended' => [], 'similar' => [], 'trending' => [], 'offers' => []];
    }

    /**
     * @param list<Produits> $catalog
     * @param array<string, float> $globalSales
     * @param array<string, float> $userProductSales
     * @param array<string, float> $userCategorySales
     * @return list<int>
     */
    private function rankInPhp(
        array $catalog,
        array $globalSales,
        array $userProductSales,
        array $userCategorySales,
        string $mode,
        int $limit
    ): array {
        $maxGlobal = max(1.0, ...array_map(static fn ($v) => (float) $v, $globalSales !== [] ? $globalSales : [0.0]));
        $maxUserProduct = max(1.0, ...array_map(static fn ($v) => (float) $v, $userProductSales !== [] ? $userProductSales : [0.0]));
        $maxUserCategory = max(1.0, ...array_map(static fn ($v) => (float) $v, $userCategorySales !== [] ? $userCategorySales : [0.0]));

        $scored = [];
        foreach ($catalog as $p) {
            $id = (int) $p->getId();
            if ($id <= 0) {
                continue;
            }
            $pid = (string) $id;
            $cat = strtoupper(trim((string) ($p->getCategory() ?? '')));

            $globalScore = ((float) ($globalSales[$pid] ?? 0.0)) / $maxGlobal;
            $userProductScore = ((float) ($userProductSales[$pid] ?? 0.0)) / $maxUserProduct;
            $userCategoryScore = ($cat !== '') ? (((float) ($userCategorySales[$cat] ?? 0.0)) / $maxUserCategory) : 0.0;

            $score = match ($mode) {
                self::MODE_TOP_SELLING => $globalScore,
                self::MODE_HISTORY => $userProductScore,
                self::MODE_CATEGORY => $userCategoryScore,
                default => (0.60 * $globalScore) + (0.25 * $userProductScore) + (0.15 * $userCategoryScore),
            };
            if ($score <= 0.0) {
                continue;
            }
            $scored[] = ['id' => $id, 'score' => $score];
        }

        usort($scored, static function (array $a, array $b): int {
            $cmp = $b['score'] <=> $a['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['id'] <=> $a['id'];
        });

        return array_map(static fn (array $row): int => (int) $row['id'], array_slice($scored, 0, $limit));
    }

    private function normalizeMode(string $mode): string
    {
        $m = strtolower(trim($mode));
        return \in_array($m, [self::MODE_TOP_SELLING, self::MODE_HISTORY, self::MODE_CATEGORY, self::MODE_HYBRID], true)
            ? $m
            : self::MODE_HYBRID;
    }

    /**
     * @param array<string, float> $signals
     */
    private function hasPositiveSignals(array $signals): bool
    {
        foreach ($signals as $v) {
            if ((float) $v > 0.0) {
                return true;
            }
        }

        return false;
    }
}
