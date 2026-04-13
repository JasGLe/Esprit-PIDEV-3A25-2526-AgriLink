<?php

namespace App\Controller\Activity;

use App\Repository\Activity\ActiviteRepository;
use App\Repository\Activity\EvenementRepository;
use App\Entity\UserManagement\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/calendar')]
#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENWEATHER_API_KEY)%')]
        private readonly string $openWeatherApiKey,
        #[Autowire('%env(OPENWEATHER_BASE_URL)%')]
        private readonly string $openWeatherBaseUrl,
    ) {
    }

    /**
     * Display calendar page
     */
    #[Route('', name: 'calendar_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->assertModuleAccess();

        $user = $this->getUser();
        $userCity = $user instanceof User ? $user->getVille() : null;
        $forecast = [];

        if ($userCity) {
            try {
                $forecast = $this->fetchForecastForCity($userCity);
            } catch (\Throwable) {
                $forecast = [];
            }
        }

        return $this->render('activity/calendar/index.html.twig', [
            'page_title' => 'Calendrier Agricole',
            'weatherForecast' => $forecast,
            'userCity' => $userCity,
        ]);
    }

    /**
     * Get events for FullCalendar (JSON API)
     * 
     * IMPORTANT CONSTRAINT: Events are displayed ONLY on their start date.
     * Multi-day activities/events appear only on the start date, not repeated.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/events', name: 'calendar_api_events', methods: ['GET'])]
    public function apiEvents(Request $request): JsonResponse
    {
        $this->assertModuleAccess();

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        try {
            $startDate = new \DateTime($start);
            $endDate = new \DateTime($end);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }

        $events = [];

        // Get activities for the date range
        // Display ONLY on start date, not repeated on end date
        $activites = $this->activiteRepository->findBetweenDates($startDate, $endDate);
        foreach ($activites as $activite) {
            if ($activite->getDateDebut()) {
                $events[] = [
                    'id' => 'activite_' . $activite->getId(),
                    'title' => $activite->getTitre(),
                    // CONSTRAINT: Only use start date, ignore end date for display
                    // This ensures multi-day events appear only once (on start date)
                    'start' => $activite->getDateDebut()->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => '#22c55e', // Green for activities
                    'borderColor' => '#16a34a',
                    'extendedProps' => [
                        'type' => 'activite',
                        'status' => $activite->getStatut(),
                        'cost' => $activite->getCoutEstime(),
                        'url' => $this->generateUrl('activite_show', ['id' => $activite->getId()]),
                    ],
                ];
            }
        }

        // Get events for the date range
        // Display ONLY on start date, not repeated
        $evenements = $this->evenementRepository->findBetweenDates($startDate, $endDate);
        foreach ($evenements as $evenement) {
            if ($evenement->getDateEvenement()) {
                $events[] = [
                    'id' => 'evenement_' . $evenement->getId(),
                    'title' => $evenement->getTitre(),
                    // CONSTRAINT: Only use event date for display
                    'start' => $evenement->getDateEvenement()->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => '#3b82f6', // Blue for events
                    'borderColor' => '#1d4ed8',
                    'extendedProps' => [
                        'type' => 'evenement',
                        'location' => $evenement->getLieu(),
                        'eventType' => $evenement->getTypeEvenement(),
                        'url' => $this->generateUrl('evenement_show', ['id' => $evenement->getId()]),
                    ],
                ];
            }
        }

        return new JsonResponse($events);
    }

    /**
     * Get weather forecast as JSON for calendar overlay
     */
    #[Route('/api/weather', name: 'calendar_api_weather', methods: ['GET'])]
    public function apiWeather(Request $request): JsonResponse
    {
        $this->assertModuleAccess();

        $city = $request->query->get('city');
        if (!$city) {
            $user = $this->getUser();
            $city = $user instanceof User ? $user->getVille() : null;
        }

        if (!$city) {
            return new JsonResponse(['error' => 'City not specified'], 400);
        }

        try {
            $forecast = $this->fetchForecastForCity($city);
            $weatherMap = [];
            foreach ($forecast as $day) {
                $dateKey = $day['dateLabel'];
                $weatherMap[$dateKey] = [
                    'icon' => $day['icon'],
                    'tempMax' => $day['tempMax'],
                    'tempMin' => $day['tempMin'],
                    'description' => $day['description'],
                ];
            }
            return new JsonResponse($weatherMap);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Unable to fetch weather'], 500);
        }
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
                'q' => sprintf('%s,TN', $city),
                'appid' => trim($this->openWeatherApiKey),
                'units' => 'metric',
                'lang' => 'fr',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
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
            $date = $dailyData['date'];
            $entry = $dailyData['entry'];

            $forecast[] = [
                'dateLabel' => $date->format('Y-m-d'),
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
     * Get events for a specific day (used in modal)
     */
    #[Route('/api/day-events', name: 'calendar_api_day_events', methods: ['GET'])]
    public function apiDayEvents(Request $request): JsonResponse
    {
        $this->assertModuleAccess();

        $date = $request->query->get('date');

        try {
            $targetDate = new \DateTime($date);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }

        $dayStart = clone $targetDate;
        $dayStart->setTime(0, 0, 0);

        $dayEnd = clone $targetDate;
        $dayEnd->setTime(23, 59, 59);

        $events = [];
        $activities = [];

        // Get activities for this day
        $activites = $this->activiteRepository->findBetweenDates($dayStart, $dayEnd);
        foreach ($activites as $activite) {
            $activities[] = [
                'type' => 'activite',
                'id' => $activite->getId(),
                'title' => $activite->getTitre(),
                'status' => $activite->getStatut(),
                'type_activite' => $activite->getTypeActivite(),
                'start' => $activite->getDateDebut()?->format('H:i'),
                'end' => $activite->getDateFin()?->format('H:i'),
                'cost' => $activite->getCoutEstime(),
                'url' => $this->generateUrl('activite_show', ['id' => $activite->getId()]),
            ];
        }

        // Get events for this day
        $evenements = $this->evenementRepository->findBetweenDates($dayStart, $dayEnd);
        foreach ($evenements as $evenement) {
            $events[] = [
                'type' => 'evenement',
                'id' => $evenement->getId(),
                'title' => $evenement->getTitre(),
                'location' => $evenement->getLieu(),
                'type_evenement' => $evenement->getTypeEvenement(),
                'time' => $evenement->getDateEvenement()?->format('H:i'),
                'description' => $evenement->getDescription(),
                'url' => $this->generateUrl('evenement_show', ['id' => $evenement->getId()]),
            ];
        }

        return new JsonResponse([
            'date' => $targetDate->format('Y-m-d'),
            'activities' => $activities,
            'events' => $events,
        ]);
    }

    /**
     * Quick create form (modal)
     */
    #[Route('/quick-create', name: 'calendar_quick_create', methods: ['GET', 'POST'])]
    public function quickCreate(Request $request): Response
    {
        $this->assertModuleAccess();

        $type = $request->query->get('type', 'activite'); // activite or evenement
        $date = $request->query->get('date');

        if ($type === 'evenement') {
            return $this->redirectToRoute('evenement_new', ['date' => $date]);
        } else {
            return $this->redirectToRoute('activite_new', ['date' => $date]);
        }
    }

    private function assertModuleAccess(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_AGRICULTEUR')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}
