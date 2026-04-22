<?php

namespace App\Service;

use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\LigneCommandeRepository;
use App\Repository\Marketplace\ProduitsRepository;
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
        private readonly string $projectDir,
    ) {
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
            $userProductSales = $signals['productScores'] ?? [];
            $userCategorySales = $signals['categoryScores'] ?? [];
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
            $catalog,
            $globalSales,
            $userProductSales,
            $userCategorySales,
            $mode,
            $limit
        );
        if ($rankedIds === []) {
            $rankedIds = $this->rankInPhp(
                $catalog,
                $globalSales,
                $userProductSales,
                $userCategorySales,
                $mode,
                $limit
            );
        }

        $byId = [];
        foreach ($catalog as $p) {
            if ($p->getId() !== null) {
                $byId[(int) $p->getId()] = $p;
            }
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
            $id = (int) ($p->getId() ?? 0);
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
                $process->setInput(json_encode($payload, JSON_UNESCAPED_UNICODE));
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
            $id = (int) ($p->getId() ?? 0);
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

