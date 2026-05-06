<?php

namespace App\Dto\Marketplace;

final class TopSellingProductDto
{
    public readonly string $productName;
    public readonly int $quantity;
    public readonly float $revenue;

    public function __construct(string $productName, int|string $quantity, int|float|string $revenue)
    {
        $this->productName = $productName;
        $this->quantity = (int) $quantity;
        $this->revenue = (float) $revenue;
    }
}
