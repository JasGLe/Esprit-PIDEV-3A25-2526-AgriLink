<?php

namespace App\Service;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;

final class PriceOptimizerService
{
    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
    ) {
    }

    /**
     * @return array{
     *   suggestedPrice: float,
     *   minPrice: float,
     *   maxPrice: float,
     *   marketAverage: float,
     *   confidence: int,
     *   rationale: string
     * }
     */
    public function suggest(string $nom, string $category, string $uniteVente, int $quantite): array
    {
        return $this->suggestWithHeuristics($nom, $category, $uniteVente, $quantite);
    }

    /**
     * @return array{
     *   suggestedPrice: float,
     *   minPrice: float,
     *   maxPrice: float,
     *   marketAverage: float,
     *   confidence: int,
     *   rationale: string
     * }
     */
    private function suggestWithHeuristics(string $nom, string $category, string $uniteVente, int $quantite): array
    {
        $candidates = $this->produitsRepository->findPublicMarketplaceCatalog(null, 'all', null, 'recent');
        $prices = [];
        $category = strtoupper(trim($category));
        $needle = mb_strtolower(trim($nom));

        foreach ($candidates as $p) {
            if (!$p instanceof Produits) {
                continue;
            }
            if (!$p->getActive() || $p->getQuantite() <= 0) {
                continue;
            }
            if ($category !== '' && strtoupper((string) ($p->getCategory() ?? '')) !== $category) {
                continue;
            }
            if ($needle !== '' && !str_contains(mb_strtolower((string) $p->getNom()), $needle)) {
                // keep product if same category even if not same name
            }
            $price = (float) $p->getPrixUnitaire();
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        if ($prices === []) {
            $base = 10.0;
            if ($category === 'FRUIT') {
                $base = 8.0;
            } elseif ($category === 'GRAINS') {
                $base = 5.0;
            }

            return [
                'suggestedPrice' => round($base, 3),
                'minPrice' => round($base * 0.9, 3),
                'maxPrice' => round($base * 1.1, 3),
                'marketAverage' => round($base, 3),
                'confidence' => 35,
                'rationale' => 'Suggestion de base faute d historique similaire. Ajustez selon qualite et saison.',
            ];
        }

        sort($prices);
        $count = count($prices);
        $avg = array_sum($prices) / $count;
        $median = $prices[(int) floor(($count - 1) / 2)];
        $min = $prices[(int) floor($count * 0.15)];
        $max = $prices[(int) floor($count * 0.85)];

        $suggested = ($avg * 0.45) + ($median * 0.55);
        if ($quantite > 200) {
            $suggested *= 0.96;
        } elseif ($quantite > 80) {
            $suggested *= 0.98;
        } elseif ($quantite < 20 && $quantite > 0) {
            $suggested *= 1.04;
        }

        if ($uniteVente === 'tonne') {
            $suggested *= 1000;
            $min *= 1000;
            $max *= 1000;
            $avg *= 1000;
        } elseif ($uniteVente === 'quintal') {
            $suggested *= 100;
            $min *= 100;
            $max *= 100;
            $avg *= 100;
        }

        $confidence = min(95, max(50, 40 + (int) floor($count * 1.2)));

        return [
            'suggestedPrice' => round($suggested, 3),
            'minPrice' => round($min, 3),
            'maxPrice' => round($max, 3),
            'marketAverage' => round($avg, 3),
            'confidence' => $confidence,
            'rationale' => 'Calcule selon prix moyens du marche, categorie, et quantite en stock.',
        ];
    }
}
