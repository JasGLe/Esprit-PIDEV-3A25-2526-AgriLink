<?php

namespace App\Dto\Marketplace;

final class CategoryRevenueDto
{
    public readonly ?string $category;
    public readonly float $revenue;

    public function __construct(string|null $category, int|float|string $revenue)
    {
        $this->category = $category;
        $this->revenue = (float) $revenue;
    }
}
