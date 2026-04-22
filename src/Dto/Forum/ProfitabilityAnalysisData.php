<?php

namespace App\Dto\Forum;

use Symfony\Component\Validator\Constraints as Assert;

class ProfitabilityAnalysisData
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Revenu est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Revenu doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Revenu doit etre superieur ou egal a zero.'),
    ])]
    public ?string $revenue = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Engrais est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Engrais doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Engrais doit etre superieur ou egal a zero.'),
    ])]
    public ?string $fertilizer = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Eau est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Eau doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Eau doit etre superieur ou egal a zero.'),
    ])]
    public ?string $water = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: "Main d'oeuvre est obligatoire."),
        new Assert\Type(type: 'numeric', message: "Main d'oeuvre doit etre numerique."),
        new Assert\GreaterThanOrEqual(value: 0, message: "Main d'oeuvre doit etre superieur ou egal a zero."),
    ])]
    public ?string $labor = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Semences est obligatoire.'),
        new Assert\Type(type: 'numeric', message: 'Semences doit etre numerique.'),
        new Assert\GreaterThanOrEqual(value: 0, message: 'Semences doit etre superieur ou egal a zero.'),
    ])]
    public ?string $seeds = null;
}
