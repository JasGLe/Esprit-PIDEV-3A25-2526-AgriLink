<?php

namespace App\Dto\Forum;

use Symfony\Component\Validator\Constraints as Assert;

class CropRecommendationData
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Le type de sol est obligatoire.'),
        new Assert\Choice(choices: ['sableux', 'argileux'], message: 'Le type de sol selectionne est invalide.'),
    ])]
    public ?string $soil = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'La saison est obligatoire.'),
        new Assert\Choice(choices: ['ete', 'hiver'], message: 'La saison selectionnee est invalide.'),
    ])]
    public ?string $season = null;
}
