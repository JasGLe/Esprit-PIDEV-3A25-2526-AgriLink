<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenWeatherMapService
{
    private const COUNTRY_CODE = 'TN';
    private const UNITS = 'metric';
    private const LANG = 'fr';
    private const REQUEST_TIMEOUT = 5.0; // seconds

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openWeatherApiKey,
        private readonly string $openWeatherBaseUrl,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Get current weather for a city.
     * Returns null if offline or API error occurs (gracefully handles no internet).
     *
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
     * Get detailed weather for a city.
     * Returns null if offline, invalid input, or API error occurs.
     *
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

        try {
            $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/weather', [
                'query' => [
                    'q' => sprintf('%s,%s', $normalizedCity, self::COUNTRY_CODE),
                    'appid' => trim($this->openWeatherApiKey),
                    'units' => self::UNITS,
                    'lang' => self::LANG,
                ],
                'timeout' => self::REQUEST_TIMEOUT,
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                $this->logWarning('OpenWeather API returned status', [
                    'city' => $normalizedCity,
                    'status' => $response->getStatusCode(),
                ]);
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
                $this->logWarning('OpenWeather API response missing required fields', [
                    'city' => $normalizedCity,
                ]);
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
        } catch (TransportExceptionInterface $e) {
            // Network error: no internet or connection timeout
            $this->logInfo('OpenWeather API unreachable (offline or timeout)', [
                'city' => $normalizedCity,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Exception $e) {
            // Catch unexpected errors without crashing the app
            $this->logError('Unexpected error in OpenWeather API call', [
                'city' => $normalizedCity,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return null;
        }
    }

    /**
     * Get 5-day forecast for a city.
     * Returns empty array if offline or API error occurs.
     *
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

        try {
            $response = $this->httpClient->request('GET', rtrim($this->openWeatherBaseUrl, '/') . '/forecast', [
                'query' => [
                    'q' => sprintf('%s,%s', $normalizedCity, self::COUNTRY_CODE),
                    'appid' => trim($this->openWeatherApiKey),
                    'units' => self::UNITS,
                    'lang' => self::LANG,
                ],
                'timeout' => self::REQUEST_TIMEOUT,
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                $this->logWarning('OpenWeather forecast API returned status', [
                    'city' => $normalizedCity,
                    'status' => $response->getStatusCode(),
                ]);
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
                    'dateKey' => $dayKey,
                    'dateLabel' => $this->formatFrenchDateLabel($date),
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
        } catch (TransportExceptionInterface $e) {
            // Network error: no internet or connection timeout
            $this->logInfo('OpenWeather forecast API unreachable (offline or timeout)', [
                'city' => $normalizedCity,
                'error' => $e->getMessage(),
            ]);
            return [];
        } catch (\Exception $e) {
            // Catch unexpected errors without crashing the app
            $this->logError('Unexpected error in OpenWeather forecast API call', [
                'city' => $normalizedCity,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return [];
        }
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

    /**
     * Logging helper: Info level
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Logging helper: Warning level
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Logging helper: Error level
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}
