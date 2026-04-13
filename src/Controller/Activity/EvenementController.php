<?php

namespace App\Controller\Activity;

use App\Entity\Activity\Evenement;
use App\Entity\UserManagement\User;
use App\Form\Activity\EvenementType;
use App\Repository\Activity\EvenementRepository;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenement')]
#[IsGranted('ROLE_USER')]
class EvenementController extends AbstractController
{
    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/list', name: 'evenement_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $this->assertModuleAccess();

        $search = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));

        $evenements = $this->evenementRepository->findBySearchAndType($search ?: null, $type ?: null);

        $viewData = [
            'evenements' => $evenements,
            'stats' => $this->buildEvenementStats($evenements),
            'filters' => [
                'q' => $search,
                'type' => $type,
            ],
            'typeOptions' => $this->evenementRepository->findAvailableTypes(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('activity/evenement/_content.html.twig', $viewData);
        }

        return $this->render('activity/evenement/list.html.twig', [
            ...$viewData,
        ]);
    }

    #[Route('/new', name: 'evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->assertModuleAccess();

        $evenement = new Evenement();
        $prefilledDate = $this->resolvePrefillDateTime($request);
        if ($prefilledDate instanceof \DateTimeImmutable) {
            $evenement->setDateEvenement($prefilledDate);
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->applyOwnerForEvenement($evenement);
                    
                    // Ensure organizer relation is set before persisting
                    if ($evenement->getOrganisateur() === null) {
                        throw new \LogicException('Organisateur must be set before saving.');
                    }
                    
                    $this->entityManager->persist($evenement);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'L\'événement a été créé avec succès.');

                    return $this->redirectBackOrFallback($request, 'evenement_list');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de l\'événement. Veuillez réessayer.');
                }
            } else {
                // Form validation failed - errors displayed in template
            }
        }

        return $this->render('activity/evenement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'evenement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        $this->assertModuleAccess();

        return $this->render('activity/evenement/show.html.twig', [
            'evenement' => $evenement,
            'canManage' => $this->canManageEvenement($evenement),
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'evenement_export_pdf', methods: ['GET'])]
    public function exportPdf(Evenement $evenement, Request $request, PdfService $pdfService): Response
    {
        if (!$this->canManageEvenement($evenement)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        try {
            $inviteeName = trim((string) $request->query->get('invitee_name', ''));
            if ($inviteeName === '') {
                $inviteeName = 'Invité(e)';
            }

            $logoDataUri = $pdfService->getImageDataUri('logo_with_text.png');

            $html = $this->renderView('activity/evenement/invitation_pdf.html.twig', [
                'evenement' => $evenement,
                'inviteeName' => $inviteeName,
                'logoDataUri' => $logoDataUri,
                'generatedAt' => new \DateTimeImmutable(),
            ]);

            $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $inviteeName) ?: 'invite';
            $fileName = sprintf('invitation-evenement-%d-%s.pdf', $evenement->getId(), $safeName);

            $pdfContent = $pdfService->renderPdfContent($html, 'A4', 'portrait');

            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
            return $this->redirectToRoute('evenement_show', ['id' => $evenement->getId()]);
        }
    }

    #[Route('/{id}/edit', name: 'evenement_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement): Response
    {
        $this->assertModuleAccess();

        if (!$this->canManageEvenement($evenement)) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres événements.');
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->applyOwnerForEvenement($evenement);
                    
                    // Ensure organizer relation is set
                    if ($evenement->getOrganisateur() === null) {
                        throw new \LogicException('Organisateur must be set before saving.');
                    }
                    
                    $this->entityManager->flush();

                    $this->addFlash('success', 'L\'événement a été modifié avec succès.');

                    return $this->redirectToRoute('evenement_show', ['id' => $evenement->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'événement. Veuillez réessayer.');
                }
            } else {
                // DEBUG: Afficher toutes les erreurs
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('warning', 'Le formulaire contient des erreurs. Veuillez les corriger avant de soumettre.');
                if (!empty($errors)) {
                    $this->addFlash('error', 'Détails des erreurs: ' . implode(' | ', $errors));
                }
            }
        }

        return $this->render('activity/evenement/edit.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/delete', name: 'evenement_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement): Response
    {
        $this->assertModuleAccess();

        if (!$this->canManageEvenement($evenement)) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres événements.');
        }

        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($evenement);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'événement a été supprimé avec succès.');
        }

        return $this->redirectBackOrFallback($request, 'evenement_list');
    }

    private function assertModuleAccess(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_AGRICULTEUR')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }

    private function canManageEvenement(Evenement $evenement): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $this->getUser();

        return $user instanceof User && $evenement->getOrganisateur()?->getId() === $user->getId();
    }

    private function applyOwnerForEvenement(Evenement $evenement): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            $evenement->setOrganisateur($user);
        }
    }

    /**
     * @param Evenement[] $evenements
     *
     * @return array<string, int|float|string>
     */
    private function buildEvenementStats(array $evenements): array
    {
        $total = count($evenements);
        $official = 0;
        $personal = 0;
        $upcoming = 0;
        $pastThisMonth = 0;
        $upcomingThisMonth = 0;
        $now = new \DateTimeImmutable();
        $nextDate = null;

        foreach ($evenements as $evenement) {
            $type = strtoupper((string) $evenement->getTypeEvenement());
            $date = $evenement->getDateEvenement();

            if ($type === 'OFFICIEL') {
                ++$official;
            } elseif ($type === 'PERSONNEL') {
                ++$personal;
            }

            if ($date >= $now) {
                ++$upcoming;

                if ($nextDate === null || $date < $nextDate) {
                    $nextDate = $date;
                }

                if ($date->format('Y-m') === $now->format('Y-m')) {
                    ++$upcomingThisMonth;
                }
            } elseif ($date->format('Y-m') === $now->format('Y-m')) {
                ++$pastThisMonth;
            }
        }

        $upcomingRate = $total > 0 ? round(($upcoming / $total) * 100, 1) : 0.0;
        $nextInDays = null;
        if ($nextDate !== null) {
            $todayDate = new \DateTimeImmutable($now->format('Y-m-d'));
            $nextEventDate = new \DateTimeImmutable($nextDate->format('Y-m-d'));
            $nextInDays = (int) $todayDate->diff($nextEventDate)->days;
        }

        return [
            'total' => $total,
            'official' => $official,
            'personal' => $personal,
            'upcoming' => $upcoming,
            'pastThisMonth' => $pastThisMonth,
            'upcomingThisMonth' => $upcomingThisMonth,
            'upcomingRate' => $upcomingRate,
            'nextDate' => $nextDate?->format('d/m/Y H:i') ?? 'Aucune date à venir',
            'nextInDays' => $nextInDays,
        ];
    }

    private function resolvePrefillDateTime(Request $request): ?\DateTimeImmutable
    {
        $datetimeParam = trim((string) $request->query->get('datetime', ''));
        if ($datetimeParam !== '') {
            $normalized = str_replace(' ', 'T', $datetimeParam);
            try {
                $dateTime = new \DateTimeImmutable($normalized);
                return $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Exception) {
            }
        }

        $startParam = trim((string) $request->query->get('start', ''));
        if ($startParam !== '') {
            try {
                $dateTime = new \DateTimeImmutable($startParam);
                return $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Exception) {
            }
        }

        $dateParam = trim((string) $request->query->get('date', ''));
        if ($dateParam === '') {
            return null;
        }

        $selectedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateParam);
        if (!$selectedDate instanceof \DateTimeImmutable) {
            return null;
        }

        $timeParam = trim((string) $request->query->get('time', ''));
        if ($timeParam !== '' && preg_match('/^\d{2}:\d{2}$/', $timeParam) === 1) {
            [$hour, $minute] = array_map('intval', explode(':', $timeParam));
            return $selectedDate->setTime($hour, $minute);
        }

        return $selectedDate->setTime(9, 0);
    }

    private function redirectBackOrFallback(Request $request, string $fallbackRoute, array $fallbackParams = []): Response
    {
        $redirectTarget = $this->sanitizeRedirectTarget((string) $request->request->get('redirect', ''), $request)
            ?? $this->sanitizeRedirectTarget((string) $request->query->get('redirect', ''), $request)
            ?? $this->sanitizeRedirectTarget((string) $request->headers->get('referer', ''), $request);

        if ($redirectTarget !== null) {
            return $this->redirect($redirectTarget);
        }

        return $this->redirectToRoute($fallbackRoute, $fallbackParams);
    }

    private function sanitizeRedirectTarget(string $target, Request $request): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return str_starts_with($target, '//') ? null : $target;
        }

        $origin = $request->getSchemeAndHttpHost();
        if (!str_starts_with($target, $origin)) {
            return null;
        }

        $parts = parse_url($target);
        if ($parts === false || (($parts['host'] ?? null) !== $request->getHost())) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $path . $query . $fragment;
    }
}
