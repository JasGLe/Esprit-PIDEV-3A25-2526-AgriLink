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

        $viewData = [
            'evenements' => $this->evenementRepository->findBySearchAndType($search ?: null, $type ?: null),
            'filters' => [
                'q' => $search,
                'type' => $type,
            ],
            'typeOptions' => $this->evenementRepository->findAvailableTypes(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('activity/evenement/_results.html.twig', $viewData);
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
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->applyOwnerForEvenement($evenement);
                    
                    // Ensure idOrganisateur is set before persisting
                    if ($evenement->getIdOrganisateur() === null) {
                        throw new \LogicException('ID organisateur must be set before saving.');
                    }
                    
                    $this->entityManager->persist($evenement);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'L\'événement a été créé avec succès.');

                    return $this->redirectToRoute('evenement_show', ['id' => $evenement->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de l\'événement. Veuillez réessayer.');
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
                    
                    // Ensure idOrganisateur is set
                    if ($evenement->getIdOrganisateur() === null) {
                        throw new \LogicException('ID organisateur must be set before saving.');
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

        return $this->redirectToRoute('evenement_list');
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

        return $user instanceof User && $evenement->getIdOrganisateur() === $user->getId();
    }

    private function applyOwnerForEvenement(Evenement $evenement): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            $evenement->setIdOrganisateur($user->getId());
        }
    }
}