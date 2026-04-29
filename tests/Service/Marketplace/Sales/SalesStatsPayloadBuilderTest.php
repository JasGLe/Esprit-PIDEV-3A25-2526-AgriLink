<?php

namespace App\Tests\Service\Marketplace\Sales;

use App\Repository\Marketplace\LigneCommandeRepository;
use App\Service\Marketplace\Sales\SalesStatsPayloadBuilder;
use PHPUnit\Framework\TestCase;

final class SalesStatsPayloadBuilderTest extends TestCase
{
    public function testBuildAggregatesRepositoryDataIntoExpectedPayloadShape(): void
    {
        $repo = $this->createMock(LigneCommandeRepository::class);

        $repo->expects($this->once())
            ->method('fetchSalesKpisForSeller')
            ->with(42)
            ->willReturn([
                'revenue_today' => 10.5,
                'revenue_month' => 300.0,
                'revenue_year' => 1200.0,
                'orders_total' => 7,
            ]);

        $repo->expects($this->once())
            ->method('fetchSalesEvolutionLast30DaysForSeller')
            ->with(42)
            ->willReturn([
                ['day_label' => '01/04', 'revenue' => 10.0],
                ['day_label' => '02/04', 'revenue' => 0.0],
            ]);

        $repo->expects($this->once())
            ->method('fetchTopSellingProductsForSeller')
            ->with(42, 6)
            ->willReturn([
                ['name' => 'Tomate', 'quantity' => 3, 'revenue' => 9.0],
            ]);

        $repo->expects($this->once())
            ->method('fetchSalesDistributionByCategoryForSeller')
            ->with(42)
            ->willReturn([
                ['category' => 'Légumes', 'revenue' => 100.0],
                ['category' => 'Fruits', 'revenue' => 50.0],
            ]);

        $builder = new SalesStatsPayloadBuilder($repo);

        $payload = $builder->build(42);

        $this->assertSame(10.5, $payload['kpis']['revenue_today']);
        $this->assertSame(7, $payload['kpis']['orders_total']);
        $this->assertSame('Tomate', $payload['top_products'][0]['name']);
        $this->assertSame(['01/04', '02/04'], $payload['evolution']['labels']);
        $this->assertSame([10.0, 0.0], $payload['evolution']['data']);
        $this->assertSame(['Légumes', 'Fruits'], $payload['categories']['labels']);
        $this->assertSame([100.0, 50.0], $payload['categories']['data']);
    }
}

