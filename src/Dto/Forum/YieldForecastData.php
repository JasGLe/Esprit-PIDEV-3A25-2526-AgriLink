<?php

namespace App\Dto\Forum;

use Symfony\Component\Validator\Constraints as Assert;

class YieldForecastData
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Surface est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Surface doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Surface doit etre superieur ou egal a zero.'),
    ])]
    public ?string $surface = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Coefficient culture est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Coefficient culture doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Coefficient culture doit etre superieur ou egal a zero.'),
    ])]
    public ?string $cropCoefficient = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Coefficient meteo est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Coefficient meteo doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Coefficient meteo doit etre superieur ou egal a zero.'),
    ])]
    public ?string $weatherCoefficient = null;
}
