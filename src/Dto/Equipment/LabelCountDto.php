<?php

namespace App\Dto\Equipment;

final class LabelCountDto
{
    public readonly ?string $label;
    public readonly int $total;

    public function __construct(string|null $label, int|string $total)
    {
        $this->label = $label;
        $this->total = (int) $total;
    }
}
