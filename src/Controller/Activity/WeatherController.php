<?php

namespace App\Controller\Activity;

use App\Entity\UserManagement\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/meteo')]
#[IsGranted('ROLE_USER')]
class WeatherController extends AbstractController
{
    private const DEFAULT_CITY = 'Tunis';
    private const COUNTRY_CODE = 'TN';
    private const UNITS = 'metric';
    private const LANG = 'fr';

    /**
     * @var array<string, string>
     */
    private const TUNISIAN_CITIES = [
        'Tunis' => 'Tunis',
        'Sfax' => 'Sfax',
        'Sousse' => 'Sousse',
        'Kairouan' => 'Kairouan',
        'Bizerte' => 'Bizerte',
        'Gabes' => 'Gabes',
        'Nabeul' => 'Nabeul',
        'Ariana' => 'Ariana',
        'Gafsa' => 'Gafsa',
        'Monastir' => 'Monastir',
        'Ben Arous' => 'Ben Arous',
        'Kasserine' => 'Kasserine',
        'Medenine' => 'Medenine',
        'Tozeur' => 'Tozeur',
        'Kebili' => 'Kebili',
        'Mahdia' => 'Mahdia',
        'Zaghouan' => 'Zaghouan',
        'Jendouba' => 'Jendouba',
        'Beja' => 'Beja',
        'Sidi Bouzid' => 'Sidi Bouzid',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENWEATHER_API_KEY)%')]
        private readonly string $openWeatherApiKey,
        #[Autowire('%env(OPENWEATHER_BASE_URL)%')]
        private readonly string $openWeatherBaseUrl,
    ) {
    }

    #[Route('', name: 'weather_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $selectedCity = trim((string) $request->query->get('city', ''));
        $searchQuery = trim((string) $request->query->get('q', ''));
        $user = $this->getUser();
        $cityFromProfile = $user instanceof User ? $this->normalizeCityName((string) $user->getVille()) : '';

        if ($selectedCity === '' && $searchQuery === '') {
            $selectedCity = $cityFromProfile ?: self::DEFAULT_CITY;
        }

        $city = $this->resolveAllowedCity($searchQuery !== '' ? $searchQuery : $selectedCity);
        $weather = null;
        $forecast = [];
        $error = null;

        try {
            $weather = $this->fetchWeatherForCity($city);
            $forecast = $this->fetchForecastForCity($city);
        } catch (\Throwable $exception) {
            $error = 'Impossible de récupérer les données météo pour le moment.';
            $this->addFlash('error', $error);
        }

        $agriInsights = is_array($weather) ? $this->buildAgricultureInsights($weather) : null;
        $forecastWithAdvice = array_map(
            fn (array $day): array => [...$day, 'agriAdvice' => $this->buildForecastAdvice($day)],
            array_slice($forecast, 0, 5)
        );

        return $this->render('activity/weather/index.html.twig', [
            'selectedCity' => $city,
            'searchQuery' => $searchQuery,
            'cityOptions' => array_values(self::TUNISIAN_CITIES),
            'weather' => $weather,
            'forecast' => $forecastWithAdvice,
            'agriInsights' => $agriInsights,
            'weatherError' => $error,
        ]);
    }

    /**
     * @return array{
     *     city: string,
     *     temperature: float,
     *     description: string,
     *     condition: string,
     *     icon: string,
     *     humidity: int,
     *     windSpeed: float,
     *     feelsLike: float,
     *     pressure: int,
     *     visibilityKm: float,
     *     updatedAt: \DateTimeImmutable
     * }
     */
    private function fetchWeatherForCity(string $city): array
    {
        if (trim($this->openWeatherApiKey) === '') {
            throw new BadRequestHttpException('OpenWeather API key is not configured.');
        }

        $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/weather', [
            'query' => [
                'q' => sprintf('%s,%s', $city, self::COUNTRY_CODE),
                'appid' => trim($this->openWeatherApiKey),
                'units' => self::UNITS,
                'lang' => self::LANG,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== Response::HTTP_OK) {
            throw new \RuntimeException(sprintf('OpenWeather responded with status %d', $statusCode));
        }

        $payload = $response->toArray(false);
        if (
            !isset(
                $payload['main']['temp'],
                $payload['main']['humidity'],
                $payload['main']['pressure'],
                $payload['wind']['speed'],
                $payload['weather'][0]['description'],
                $payload['weather'][0]['icon']
            )
        ) {
            throw new \RuntimeException('Unexpected weather payload.');
        }

        $updatedAt = isset($payload['dt']) ? (new \DateTimeImmutable())->setTimestamp((int) $payload['dt']) : new \DateTimeImmutable();

        return [
            'city' => (string) ($payload['name'] ?? $city),
            'temperature' => (float) $payload['main']['temp'],
            'description' => ucfirst((string) $payload['weather'][0]['description']),
            'condition' => (string) ($payload['weather'][0]['main'] ?? ''),
            'icon' => (string) $payload['weather'][0]['icon'],
            'humidity' => (int) $payload['main']['humidity'],
            'windSpeed' => (float) $payload['wind']['speed'],
            'feelsLike' => (float) ($payload['main']['feels_like'] ?? $payload['main']['temp']),
            'pressure' => (int) $payload['main']['pressure'],
            'visibilityKm' => round(((float) ($payload['visibility'] ?? 0)) / 1000, 1),
            'updatedAt' => $updatedAt,
        ];
    }

    /**
     * @return list<array{
     *     dateLabel: string,
     *     icon: string,
     *     description: string,
     *     tempMin: float,
     *     tempMax: float,
     *     humidity: int,
     *     windSpeed: float,
     *     condition: string
     * }>
     */
    private function fetchForecastForCity(string $city): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/forecast', [
            'query' => [
                'q' => sprintf('%s,%s', $city, self::COUNTRY_CODE),
                'appid' => trim($this->openWeatherApiKey),
                'units' => self::UNITS,
                'lang' => self::LANG,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('Unable to retrieve forecast data.');
        }

        $payload = $response->toArray(false);
        $items = $payload['list'] ?? null;
        if (!is_array($items)) {
            throw new \RuntimeException('Unexpected forecast payload.');
        }

        $dailyBest = [];

        foreach ($items as $item) {
            if (
                !isset(
                    $item['dt'],
                    $item['main']['temp_min'],
                    $item['main']['temp_max'],
                    $item['main']['humidity'],
                    $item['wind']['speed'],
                    $item['weather'][0]['description'],
                    $item['weather'][0]['icon']
                )
            ) {
                continue;
            }

            $date = (new \DateTimeImmutable())->setTimestamp((int) $item['dt']);
            $dayKey = $date->format('Y-m-d');
            $hour = (int) $date->format('H');
            $distanceToNoon = abs(12 - $hour);

            if (!isset($dailyBest[$dayKey]) || $distanceToNoon < $dailyBest[$dayKey]['distanceToNoon']) {
                $dailyBest[$dayKey] = [
                    'distanceToNoon' => $distanceToNoon,
                    'date' => $date,
                    'entry' => $item,
                ];
            }
        }

        ksort($dailyBest);

        $forecast = [];
        foreach (array_slice($dailyBest, 0, 5) as $dailyData) {
            /** @var \DateTimeImmutable $date */
            $date = $dailyData['date'];
            /** @var array<string, mixed> $entry */
            $entry = $dailyData['entry'];

            $forecast[] = [
                'dateLabel' => $this->formatFrenchDateLabel($date),
                'icon' => (string) $entry['weather'][0]['icon'],
                'description' => ucfirst((string) $entry['weather'][0]['description']),
                'tempMin' => (float) $entry['main']['temp_min'],
                'tempMax' => (float) $entry['main']['temp_max'],
                'humidity' => (int) $entry['main']['humidity'],
                'windSpeed' => (float) $entry['wind']['speed'],
                'condition' => (string) ($entry['weather'][0]['main'] ?? ''),
            ];
        }

        return $forecast;
    }

    /**
     * @param array{
     *   city: string,
     *   temperature: float,
     *   condition: string,
     *   humidity: int,
     *   windSpeed: float
     * } $weather
     *
     * @return array{
     *   suitability: string,
     *   suitabilityClass: string,
     *   suitabilityIcon: string,
     *   irrigation: string,
     *   irrigationIcon: string,
     *   cropFriendly: string,
     *   cropFriendlyIcon: string,
     *   advice: string
     * }
     */
    private function buildAgricultureInsights(array $weather): array
    {
        $temperature = (float) $weather['temperature'];
        $humidity = (int) $weather['humidity'];
        $windSpeed = (float) $weather['windSpeed'];
        $condition = strtoupper((string) $weather['condition']);

        $score = 100;

        if (in_array($condition, ['THUNDERSTORM', 'SNOW'], true)) {
            $score -= 45;
        } elseif (in_array($condition, ['RAIN', 'DRIZZLE'], true)) {
            $score -= 25;
        } elseif ($condition === 'CLOUDS') {
            $score -= 8;
        }

        if ($temperature < 8 || $temperature > 36) {
            $score -= 30;
        } elseif ($temperature < 13 || $temperature > 32) {
            $score -= 15;
        }

        if ($humidity < 25 || $humidity > 85) {
            $score -= 15;
        } elseif ($humidity < 35 || $humidity > 75) {
            $score -= 8;
        }

        if ($windSpeed > 10) {
            $score -= 20;
        } elseif ($windSpeed > 6) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        if ($score >= 70) {
            $suitability = 'Bonnes conditions';
            $suitabilityClass = 'agri-status--good';
            $suitabilityIcon = '🌱';
        } elseif ($score >= 45) {
            $suitability = 'Conditions moyennes';
            $suitabilityClass = 'agri-status--medium';
            $suitabilityIcon = '🌾';
        } else {
            $suitability = 'Conditions défavorables';
            $suitabilityClass = 'agri-status--bad';
            $suitabilityIcon = '⚠️';
        }

        if (in_array($condition, ['RAIN', 'DRIZZLE', 'THUNDERSTORM'], true) || $humidity > 75) {
            $irrigation = 'Irrigation non recommandée';
            $irrigationIcon = '💧';
        } elseif ($temperature >= 28 && $humidity < 55) {
            $irrigation = 'Irrigation recommandée';
            $irrigationIcon = '🚿';
        } else {
            $irrigation = 'Irrigation optionnelle';
            $irrigationIcon = '🪴';
        }

        if ($temperature >= 12 && $temperature <= 24 && $humidity >= 45 && $humidity <= 75) {
            $cropFriendly = 'Favorables pour blé et légumineuses';
            $cropFriendlyIcon = '🌾';
        } elseif ($temperature >= 18 && $temperature <= 32 && $humidity >= 35 && $humidity <= 70) {
            $cropFriendly = 'Favorables pour oliviers et maraîchage';
            $cropFriendlyIcon = '🫒';
        } else {
            $cropFriendly = 'Privilégier cultures résistantes';
            $cropFriendlyIcon = '🌿';
        }

        $advice = match (true) {
            in_array($condition, ['THUNDERSTORM', 'SNOW'], true) => 'Évitez les travaux en plein champ et sécurisez le matériel.',
            in_array($condition, ['RAIN', 'DRIZZLE'], true) => 'Reporter l’arrosage et privilégier les contrôles de drainage.',
            $windSpeed > 8 => 'Limiter les traitements foliaires à cause du vent.',
            $temperature > 32 => 'Privilégier les interventions tôt le matin et surveiller le stress hydrique.',
            default => 'Fenêtre favorable pour entretien, semis légers et suivi phytosanitaire.',
        };

        return [
            'suitability' => $suitability,
            'suitabilityClass' => $suitabilityClass,
            'suitabilityIcon' => $suitabilityIcon,
            'irrigation' => $irrigation,
            'irrigationIcon' => $irrigationIcon,
            'cropFriendly' => $cropFriendly,
            'cropFriendlyIcon' => $cropFriendlyIcon,
            'advice' => $advice,
        ];
    }

    /**
     * @param array{
     *   tempMax: float,
     *   humidity: int,
     *   windSpeed: float,
     *   condition: string
     * } $day
     */
    private function buildForecastAdvice(array $day): string
    {
        $condition = strtoupper((string) $day['condition']);
        $tempMax = (float) $day['tempMax'];
        $humidity = (int) $day['humidity'];
        $wind = (float) $day['windSpeed'];

        if (in_array($condition, ['RAIN', 'DRIZZLE', 'THUNDERSTORM'], true)) {
            return 'Pluie attendue: réduire l’arrosage et surveiller l’état du sol.';
        }

        if ($tempMax > 31 && $humidity < 50) {
            return 'Risque de stress hydrique: arrosage matinal conseillé.';
        }

        if ($wind > 8) {
            return 'Vent soutenu: éviter pulvérisation et traitement foliaire.';
        }

        return 'Conditions stables: période correcte pour interventions culturales.';
    }

    private function resolveAllowedCity(string $city): string
    {
        $normalizedCity = $this->normalizeCityName($city);
        if ($normalizedCity === '' || !isset(self::TUNISIAN_CITIES[$normalizedCity])) {
            return self::DEFAULT_CITY;
        }

        return self::TUNISIAN_CITIES[$normalizedCity];
    }

    private function normalizeCityName(string $city): string
    {
        $normalized = trim($city);
        if ($normalized === '') {
            return '';
        }

        return ucwords(strtolower($normalized));
    }

    private function formatFrenchDateLabel(\DateTimeImmutable $date): string
    {
        $days = [
            'Mon' => 'Lun',
            'Tue' => 'Mar',
            'Wed' => 'Mer',
            'Thu' => 'Jeu',
            'Fri' => 'Ven',
            'Sat' => 'Sam',
            'Sun' => 'Dim',
        ];

        $months = [
            'Jan' => 'janv',
            'Feb' => 'fevr',
            'Mar' => 'mars',
            'Apr' => 'avr',
            'May' => 'mai',
            'Jun' => 'juin',
            'Jul' => 'juil',
            'Aug' => 'aout',
            'Sep' => 'sept',
            'Oct' => 'oct',
            'Nov' => 'nov',
            'Dec' => 'dec',
        ];

        $day = $days[$date->format('D')] ?? $date->format('D');
        $month = $months[$date->format('M')] ?? $date->format('M');

        return sprintf('%s %s %s', $day, $date->format('d'), $month);
    }

}
