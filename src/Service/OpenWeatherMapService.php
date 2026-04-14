<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenWeatherMapService
{
    private const COUNTRY_CODE = 'TN';
    private const UNITS = 'metric';
    private const LANG = 'fr';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openWeatherApiKey,
        private readonly string $openWeatherBaseUrl,
    ) {
    }

    /**
     * @return array{temperature: float, icon: string, description: string}|null
     */
    public function getCurrentWeatherForCity(?string $city): ?array
    {
        $weather = $this->getDetailedWeatherForCity($city);
        if ($weather === null) {
            return null;
        }

        return [
            'temperature' => $weather['temperature'],
            'icon' => $weather['icon'],
            'description' => $weather['description'],
        ];
    }

    /**
     * @return array{
     *   city: string,
     *   temperature: float,
     *   description: string,
     *   condition: string,
     *   icon: string,
     *   humidity: int,
     *   windSpeed: float,
     *   feelsLike: float,
     *   pressure: int,
     *   visibilityKm: float,
     *   updatedAt: \DateTimeImmutable
     * }|null
     */
    public function getDetailedWeatherForCity(?string $city): ?array
    {
        $normalizedCity = trim((string) $city);
        if ($normalizedCity === '' || trim($this->openWeatherApiKey) === '') {
            return null;
        }

        $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/weather', [
            'query' => [
                'q' => sprintf('%s,%s', $normalizedCity, self::COUNTRY_CODE),
                'appid' => trim($this->openWeatherApiKey),
                'units' => self::UNITS,
                'lang' => self::LANG,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return null;
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
            return null;
        }

        $updatedAt = isset($payload['dt']) ? (new \DateTimeImmutable())->setTimestamp((int) $payload['dt']) : new \DateTimeImmutable();

        return [
            'city' => (string) ($payload['name'] ?? $normalizedCity),
            'temperature' => (float) $payload['main']['temp'],
            'condition' => (string) ($payload['weather'][0]['main'] ?? ''),
            'icon' => (string) $payload['weather'][0]['icon'],
            'description' => ucfirst((string) $payload['weather'][0]['description']),
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
     *   dateLabel: string,
     *   icon: string,
     *   description: string,
     *   tempMin: float,
     *   tempMax: float,
     *   humidity: int,
     *   windSpeed: float,
     *   condition: string
     * }>
     */
    public function getForecastForCity(?string $city): array
    {
        $normalizedCity = trim((string) $city);
        if ($normalizedCity === '' || trim($this->openWeatherApiKey) === '') {
            return [];
        }

        $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/forecast', [
            'query' => [
                'q' => sprintf('%s,%s', $normalizedCity, self::COUNTRY_CODE),
                'appid' => trim($this->openWeatherApiKey),
                'units' => self::UNITS,
                'lang' => self::LANG,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return [];
        }

        $payload = $response->toArray(false);
        $items = $payload['list'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $dailyBest = [];
        $dailyStats = [];

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

            // Track the representative point for display (closest to noon)
            if (!isset($dailyBest[$dayKey]) || $distanceToNoon < $dailyBest[$dayKey]['distanceToNoon']) {
                $dailyBest[$dayKey] = [
                    'distanceToNoon' => $distanceToNoon,
                    'date' => $date,
                    'entry' => $item,
                ];
            }

            // Calculate actual daily min/max across all day points
            if (!isset($dailyStats[$dayKey])) {
                $dailyStats[$dayKey] = [
                    'tempMin' => PHP_FLOAT_MAX,
                    'tempMax' => PHP_FLOAT_MIN,
                ];
            }

            $tempMin = (float) $item['main']['temp_min'];
            $tempMax = (float) $item['main']['temp_max'];

            $dailyStats[$dayKey]['tempMin'] = min($dailyStats[$dayKey]['tempMin'], $tempMin);
            $dailyStats[$dayKey]['tempMax'] = max($dailyStats[$dayKey]['tempMax'], $tempMax);
        }

        ksort($dailyBest);

        $forecast = [];
        foreach (array_slice($dailyBest, 0, 5) as $dailyData) {
            /** @var \DateTimeImmutable $date */
            $date = $dailyData['date'];
            /** @var array<string, mixed> $entry */
            $entry = $dailyData['entry'];
            $dayKey = $date->format('Y-m-d');

            $forecast[] = [
                'dateKey' => $dayKey, // Format: YYYY-MM-DD (for FullCalendar data-date matching)
                'dateLabel' => $this->formatFrenchDateLabel($date), // Format: Lun 14 janv (for UI display)
                'icon' => (string) $entry['weather'][0]['icon'],
                'description' => ucfirst((string) $entry['weather'][0]['description']),
                'tempMin' => $dailyStats[$dayKey]['tempMin'] ?? (float) $entry['main']['temp_min'],
                'tempMax' => $dailyStats[$dayKey]['tempMax'] ?? (float) $entry['main']['temp_max'],
                'humidity' => (int) $entry['main']['humidity'],
                'windSpeed' => (float) $entry['wind']['speed'],
                'condition' => (string) ($entry['weather'][0]['main'] ?? ''),
            ];
        }

        return $forecast;
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
