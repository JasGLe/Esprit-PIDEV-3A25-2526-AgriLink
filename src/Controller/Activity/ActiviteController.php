<?php

namespace App\Controller\Activity;

use App\Entity\Activity\Activite;
use App\Entity\UserManagement\User;
use App\Form\Activity\ActiviteType;
use App\Repository\Activity\ActiviteRepository;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activite')]
#[IsGranted('ROLE_USER')]
class ActiviteController extends AbstractController
{
    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/list', name: 'activite_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $this->assertModuleAccess();

        $search = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));
        $status = trim((string) $request->query->get('status', ''));
        $user = $this->getUser();
        $connectedUserId = $user instanceof User ? $user->getId() : null;

        $activites = $this->activiteRepository->findBySearchTypeAndStatus($search ?: null, $type ?: null, $status ?: null);

        $viewData = [
            'activites' => $activites,
            'stats' => $this->buildActiviteStats($activites, $connectedUserId),
            'filters' => [
                'q' => $search,
                'type' => $type,
                'status' => $status,
            ],
            'typeOptions' => $this->activiteRepository->findAvailableTypes(),
            'statusOptions' => $this->activiteRepository->findAvailableStatuses(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('activity/activite/_content.html.twig', $viewData);
        }

        return $this->render('activity/activite/list.html.twig', [
            ...$viewData,
        ]);
    }

    #[Route('/new', name: 'activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->assertModuleAccess();

        $activite = new Activite();
        $dateParam = trim((string) $request->query->get('date', ''));
        if ($dateParam !== '') {
            $selectedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateParam);
            if ($selectedDate instanceof \DateTimeImmutable) {
                $activite->setDateDebut($selectedDate->setTime(9, 0));
            }
        }

        $form = $this->createForm(ActiviteType::class, $activite, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->applyOwnerForActivite($activite);
                    
                    // Ensure idAgriculteur is set before persisting
                    if ($activite->getIdAgriculteur() === null) {
                        throw new \LogicException('ID agriculteur must be set before saving.');
                    }
                    
                    $this->entityManager->persist($activite);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'L\'activité a été créée avec succès.');

                    return $this->redirectToRoute('activite_show', ['id' => $activite->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de l\'activité. Veuillez réessayer.');
                }
            } else {
                // Form validation failed - errors displayed in template
            }
        }

        return $this->render('activity/activite/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'activite_export_pdf', methods: ['GET'])]
    public function exportPdf(Activite $activite, Request $request, PdfService $pdfService): Response
    {
        if (!$this->canManageActivite($activite)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        try {
            $logoDataUri = $pdfService->getImageDataUri('logo_with_text.png');

            $html = $this->renderView('activity/activite/detail_pdf.html.twig', [
                'activite' => $activite,
                'logoDataUri' => $logoDataUri,
                'generatedAt' => new \DateTimeImmutable(),
            ]);

            $fileName = sprintf('extrait-activite-%d.pdf', $activite->getId());
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
            return $this->redirectToRoute('activite_show', ['id' => $activite->getId()]);
        }
    }

    #[Route('/{id}/export-excel', name: 'activite_export_excel', methods: ['GET'])]
    public function exportExcel(Activite $activite, Request $request): Response
    {
        if (!$this->canManageActivite($activite)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        try {
            $content = $this->renderView('activity/activite/detail_excel.html.twig', [
                'activite' => $activite,
                'generatedAt' => new \DateTimeImmutable(),
            ]);

            $fileName = sprintf('extrait-activite-%d.xls', $activite->getId());

            return new Response(
                $content,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/vnd.ms-excel',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du fichier Excel: ' . $e->getMessage());
            return $this->redirectToRoute('activite_show', ['id' => $activite->getId()]);
        }
    }

    #[Route('/export', name: 'activite_export', methods: ['GET'])]
    public function export(Request $request, PdfService $pdfService): Response
    {
        $this->assertModuleAccess();

        try {
            $startDateRaw = (string) $request->query->get('start_date', '');
            $endDateRaw = (string) $request->query->get('end_date', '');
            $format = strtolower(trim((string) $request->query->get('format', 'pdf')));

            $startDate = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDateRaw . ' 00:00:00');
            $endDate = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDateRaw . ' 23:59:59');

            if (!$startDate || !$endDate || $endDate < $startDate) {
                throw new \InvalidArgumentException('Intervalle de dates invalide.');
            }

            $activites = $this->activiteRepository->findBetweenDates($startDate, $endDate);
            $logoDataUri = $pdfService->getImageDataUri('logo_with_text.png');
            $title = sprintf('Extraits d\'activités du %s au %s', $startDate->format('d/m/Y'), $endDate->format('d/m/Y'));

            if ($format === 'excel') {
                $fileName = sprintf('activites-%s-%s.xls', $startDate->format('Ymd'), $endDate->format('Ymd'));
                $content = $this->renderView('activity/activite/export_excel.html.twig', [
                    'activites' => $activites,
                    'title' => $title,
                    'logoDataUri' => $logoDataUri,
                    'generatedAt' => new \DateTimeImmutable(),
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]);

                return new Response(
                    $content,
                    Response::HTTP_OK,
                    [
                        'Content-Type' => 'application/vnd.ms-excel',
                        'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                        'Pragma' => 'no-cache',
                        'Expires' => '0',
                    ]
                );
            }

            $html = $this->renderView('activity/activite/export_pdf.html.twig', [
                'activites' => $activites,
                'title' => $title,
                'logoDataUri' => $logoDataUri,
                'generatedAt' => new \DateTimeImmutable(),
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            $fileName = sprintf('activites-%s-%s.pdf', $startDate->format('Ymd'), $endDate->format('Ymd'));
            $pdfContent = $pdfService->renderPdfContent($html, 'A4', 'landscape');

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
            $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());
            return $this->redirectToRoute('activite_list');
        }
    }

    #[Route('/{id}', name: 'activite_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        $this->assertModuleAccess();

        return $this->render('activity/activite/show.html.twig', [
            'activite' => $activite,
            'canManage' => $this->canManageActivite($activite),
        ]);
    }

    #[Route('/{id}/edit', name: 'activite_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Activite $activite): Response
    {
        $this->assertModuleAccess();

        if (!$this->canManageActivite($activite)) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres activités.');
        }

        $form = $this->createForm(ActiviteType::class, $activite, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->applyOwnerForActivite($activite);
                    
                    // Ensure idAgriculteur is set
                    if ($activite->getIdAgriculteur() === null) {
                        throw new \LogicException('ID agriculteur must be set before saving.');
                    }
                    
                    $this->entityManager->flush();

                    $this->addFlash('success', 'L\'activité a été modifiée avec succès.');

                    return $this->redirectToRoute('activite_show', ['id' => $activite->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'activité. Veuillez réessayer.');
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

        return $this->render('activity/activite/edit.html.twig', [
            'form' => $form->createView(),
            'activite' => $activite,
        ]);
    }

    #[Route('/{id}/delete', name: 'activite_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Activite $activite): Response
    {
        $this->assertModuleAccess();

        if (!$this->canManageActivite($activite)) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres activités.');
        }

        if ($this->isCsrfTokenValid('delete' . $activite->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($activite);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'activité a été supprimée avec succès.');
        }

        return $this->redirectToRoute('activite_list');
    }

    private function assertModuleAccess(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_AGRICULTEUR')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }

    private function canManageActivite(Activite $activite): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $this->getUser();

        return $user instanceof User && $activite->getIdAgriculteur() === $user->getId();
    }

    private function applyOwnerForActivite(Activite $activite): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            $activite->setIdAgriculteur($user->getId());
        }
    }

    /**
     * @param Activite[] $activites
     *
     * @return array<string, int|float>
     */
    private function buildActiviteStats(array $activites, ?int $connectedUserId): array
    {
        $total = count($activites);
        $planned = 0;
        $inProgress = 0;
        $finished = 0;
        $totalCost = 0.0;

        foreach ($activites as $activite) {
            $status = strtoupper((string) $activite->getStatut());

            if ($status === 'PLANIFIEE') {
                ++$planned;
            } elseif ($status === 'EN_COURS') {
                ++$inProgress;
            } elseif ($status === 'TERMINEE') {
                ++$finished;
            }

            $costValue = $activite->getCoutEstime();
            if (
                $connectedUserId !== null
                && $activite->getIdAgriculteur() === $connectedUserId
                && $costValue !== null
                && $costValue !== ''
            ) {
                $totalCost += (float) str_replace(',', '.', (string) $costValue);
            }
        }

        $completionRate = $total > 0 ? round(($finished / $total) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'planned' => $planned,
            'inProgress' => $inProgress,
            'finished' => $finished,
            'completionRate' => $completionRate,
            'totalCost' => round($totalCost, 2),
        ];
    }
}