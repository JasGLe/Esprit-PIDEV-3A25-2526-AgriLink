<?php

namespace App\Controller\Activity;

use App\Repository\Activity\ActiviteRepository;
use App\Repository\Activity\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly EvenementRepository $evenementRepository,
    ) {
    }

    /**
     * Display calendar page
     */
    #[Route('', name: 'calendar_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->assertModuleAccess();

        return $this->render('activity/calendar/index.html.twig', [
            'page_title' => 'Calendrier Agricole',
        ]);
    }

    /**
     * Get events for FullCalendar (JSON API)
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
        $activites = $this->activiteRepository->findBetweenDates($startDate, $endDate);
        foreach ($activites as $activite) {
            if ($activite->getDateDebut()) {
                $events[] = [
                    'id' => 'activite_' . $activite->getId(),
                    'title' => $activite->getTitre(),
                    'start' => $activite->getDateDebut()->format('Y-m-d\TH:i:s'),
                    'end' => $activite->getDateFin() ? $activite->getDateFin()->format('Y-m-d\TH:i:s') : $activite->getDateDebut()->format('Y-m-d\TH:i:s'),
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
        $evenements = $this->evenementRepository->findBetweenDates($startDate, $endDate);
        foreach ($evenements as $evenement) {
            if ($evenement->getDateEvenement()) {
                $events[] = [
                    'id' => 'evenement_' . $evenement->getId(),
                    'title' => $evenement->getTitre(),
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
