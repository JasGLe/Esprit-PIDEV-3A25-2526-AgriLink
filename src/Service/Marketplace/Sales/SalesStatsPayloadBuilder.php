<?php

namespace App\Service\Marketplace\Sales;

use App\Repository\Marketplace\LigneCommandeRepository;

final class SalesStatsPayloadBuilder
{
    public function __construct(
        private readonly LigneCommandeRepository $ligneCommandeRepository,
    ) {
    }

    /**
     * @return array{
     *   kpis: array{revenue_today: float, revenue_month: float, revenue_year: float, orders_total: int},
     *   top_products: list<array{name: string, quantity: int, revenue: float}>,
     *   evolution: array{labels: list<string>, data: list<float>},
     *   categories: array{labels: list<string>, data: list<float>}
     * }
     */
    public function build(int $sellerUserId): array
    {
        $kpis = $this->ligneCommandeRepository->fetchSalesKpisForSeller($sellerUserId);
        $evolutionRows = $this->ligneCommandeRepository->fetchSalesEvolutionLast30DaysForSeller($sellerUserId);
        $topProducts = $this->ligneCommandeRepository->fetchTopSellingProductsForSeller($sellerUserId, 6);
        $categoryRows = $this->ligneCommandeRepository->fetchSalesDistributionByCategoryForSeller($sellerUserId);

        return [
            'kpis' => $kpis,
            'top_products' => $topProducts,
            'evolution' => [
                'labels' => array_map(static fn (array $r): string => (string) ($r['day_label'] ?? ''), $evolutionRows),
                'data' => array_map(static fn (array $r): float => (float) ($r['revenue'] ?? 0.0), $evolutionRows),
            ],
            'categories' => [
                'labels' => array_map(static fn (array $r): string => (string) ($r['category'] ?? ''), $categoryRows),
                'data' => array_map(static fn (array $r): float => (float) ($r['revenue'] ?? 0.0), $categoryRows),
            ],
        ];
    }
}

