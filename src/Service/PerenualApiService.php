<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PerenualApiService
{
    private const BASE_URL = 'https://perenual.com/api';

    private const TYPE_KEYWORDS = [
        'OLEICULTURE'             => 'olive',
        'GRANDES_CULTURES'        => 'wheat',
        'MARAICHAGE'              => 'vegetable',
        'ARBORICULTURE_FRUITIERE' => 'fruit',
        'PHOENICICULTURE'         => 'date palm',
        'CULTURES_FOURRAGERES'    => 'fodder',
    ];

   
    private const CULTURES_PAR_TYPE = [
        'OLEICULTURE' => [
            'Olivier', 'Oleaster', 'Arbequina', 'Picholine', 'Chemlali',
        ],
        'GRANDES_CULTURES' => [
            'Blé dur', 'Blé tendre', 'Orge', 'Maïs', 'Tournesol',
            'Soja', 'Sorgho', 'Triticale',
        ],
        'MARAICHAGE' => [
            'Tomate', 'Poivron', 'Courgette', 'Concombre', 'Aubergine',
            'Laitue', 'Carotte', 'Oignon', 'Ail', 'Haricot vert',
            'Pois chiche', 'Pomme de terre',
        ],
        'ARBORICULTURE_FRUITIERE' => [
            'Abricotier', 'Pêcher', 'Amandier', 'Grenadier', 'Figuier',
            'Pommier', 'Poirier', 'Cerisier', 'Vigne',
        ],
        'PHOENICICULTURE' => [
            'Deglet Nour', 'Medjool', 'Khadrawy', 'Zahidi', 'Halawi',
        ],
        'CULTURES_FOURRAGERES' => [
            'Luzerne', 'Vesce', 'Avoine fourragère', 'Sorgho fourrager',
            'Trèfle', 'Ray-grass',
        ],
    ];

    public function __construct(
        private HttpClientInterface $http,
        private string              $apiKey
    ) {}

    /**
     * Retourne la liste des noms de cultures pour un type donné
     * @return string[]
     */
    public function getCulturesByType(string $type): array
    {
        return self::CULTURES_PAR_TYPE[$type] ?? [];
    }

    /**
     * Appelle Perenual et retourne saison + dates suggérées
     * @return array<string, mixed>|null
     */
    public function resolve(string $nomCulture, string $typeCulture): ?array
    {
        try {
            // Rechercher la plante par nom
            $response = $this->http->request('GET', self::BASE_URL . '/species-list', [
                'query' => [
                    'key'    => $this->apiKey,
                    'q'      => $nomCulture,
                    'page'   => 1,
                ],
                'timeout' => 8,
            ]);

            $data = $response->toArray();

            if (empty($data['data'])) {
                
                return $this->resolveByKeyword(
                    self::TYPE_KEYWORDS[$typeCulture] ?? $nomCulture
                );
            }

            $plant = $data['data'][0];

            return $this->buildResult($plant, $typeCulture);

        } catch (\Exception $e) {
            return $this->fallbackResult($typeCulture);
        }
    }

    /**
     * Recherche par mot-clé type si le nom exact ne donne rien
     * @return array<string, mixed>|null
     */
    private function resolveByKeyword(string $keyword): ?array
    {
        try {
            $response = $this->http->request('GET', self::BASE_URL . '/species-list', [
                'query' => ['key' => $this->apiKey, 'q' => $keyword, 'page' => 1],
                'timeout' => 6,
            ]);
            $data = $response->toArray();
            if (!empty($data['data'])) {
                return $this->buildResult($data['data'][0], null);
            }
        } catch (\Exception $e) {}

        return null;
    }

    /**
     * Construit le résultat depuis la réponse Perenual
     * @param array<string, mixed> $plant
     * @return array<string, mixed>
     */
    private function buildResult(array $plant, ?string $typeCulture): array
    {
        
        $saison = $this->extractSaison($plant, $typeCulture);
        [$dateSemis, $dateRecolte] = $this->computeDates($saison, $typeCulture);

        return [
            'saison'      => $saison,
            'dateSemis'   => $dateSemis,
            'dateRecolte' => $dateRecolte,
            'source'      => 'perenual',
            'plantName'   => $plant['common_name'] ?? $plant['scientific_name'][0] ?? '—',
            'cycle'       => $plant['cycle'] ?? null,
            'watering'    => $plant['watering'] ?? null,
            'sunlight'    => $plant['sunlight'][0] ?? null,
        ];
    }

    /**
     * Détermine la saison à partir des données Perenual
     * @param array<string, mixed> $plant
     */
    private function extractSaison(array $plant, ?string $type): string
    {
        
        $cycle = strtolower($plant['cycle'] ?? '');

        
        $saisonParType = [
            'OLEICULTURE'             => 'AUTOMNE',
            'GRANDES_CULTURES'        => 'HIVER',
            'MARAICHAGE'              => 'PRINTEMPS',
            'ARBORICULTURE_FRUITIERE' => 'PRINTEMPS',
            'PHOENICICULTURE'         => 'ETE',
            'CULTURES_FOURRAGERES'    => 'HIVER',
        ];

        if ($type && isset($saisonParType[$type])) {
            return $saisonParType[$type];
        }

       
        if (str_contains($cycle, 'annual')) return 'PRINTEMPS';
        if (str_contains($cycle, 'perennial')) return 'AUTOMNE';

        return 'PRINTEMPS';
    }

    /**
     * Calcule dateSemis et dateRecolte selon la saison
     * @return array{0: string, 1: string}
     */
    private function computeDates(string $saison, ?string $type): array
    {
        $today = new \DateTimeImmutable();

        // Durées de culture en mois par type
        $dureeMois = [
            'OLEICULTURE'             => 8,
            'GRANDES_CULTURES'        => 6,
            'MARAICHAGE'              => 4,
            'ARBORICULTURE_FRUITIERE' => 7,
            'PHOENICICULTURE'         => 9,
            'CULTURES_FOURRAGERES'    => 5,
        ];

        $duree = $dureeMois[$type] ?? 5;

        // Date de semis selon la saison
        $moisSemis = match($saison) {
            'PRINTEMPS' => 3,   // Mars
            'ETE'       => 5,   // Mai
            'AUTOMNE'   => 9,   // Septembre
            'HIVER'     => 11,  // Novembre
            default     => 3,
        };

        $annee    = (int) $today->format('Y');
        $moisActuel = (int) $today->format('n');

        // Si le mois de semis est déjà passé → année suivante
        if ($moisSemis < $moisActuel) {
            $annee++;
        }

        $dateSemis   = new \DateTimeImmutable("{$annee}-{$moisSemis}-01");
        $dateRecolte = $dateSemis->modify("+{$duree} months");

        return [
            $dateSemis->format('Y-m-d'),
            $dateRecolte->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackResult(string $typeCulture): array
    {
        $saison = 'PRINTEMPS';
        [$dateSemis, $dateRecolte] = $this->computeDates($saison, $typeCulture);

        return [
            'saison'      => $saison,
            'dateSemis'   => $dateSemis,
            'dateRecolte' => $dateRecolte,
            'source'      => 'fallback',
            'plantName'   => null,
            'cycle'       => null,
            'watering'    => null,
            'sunlight'    => null,
        ];
    }
}