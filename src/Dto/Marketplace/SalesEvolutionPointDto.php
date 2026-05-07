<?php

namespace App\Dto\Marketplace;

final class SalesEvolutionPointDto
{
    public readonly \DateTimeInterface|null $dayDate;
    public readonly float $revenue;

    public function __construct(\DateTimeInterface|null $dayDate, int|float|string $revenue)
    {
        $this->dayDate = $dayDate;
        $this->revenue = (float) $revenue;
    }
}
