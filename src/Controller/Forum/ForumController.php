<?php

namespace App\Controller\Forum;

use App\Dto\Forum\ProfitabilityAnalysisData;
use App\Dto\Forum\YieldForecastData;
use App\Entity\Forum\Forum;
use App\Entity\Forum\Message;
use App\Entity\UserManagement\User;
use App\Form\Forum\ForumType;
use App\Form\Forum\ProfitabilityAnalysisType;
use App\Form\Forum\YieldForecastType;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\MessageRepository;
use App\Service\ForumMessageTranslationException;
use App\Service\ForumMessageTranslationService;
use App\Service\ForumVoiceTranscriptionService;
use App\Service\ForumAiAssistantException;
use App\Service\ForumAiAssistantService;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/forum')]
class ForumController extends AbstractController
{
    private const MAX_AUDIO_SIZE = 10 * 1024 * 1024;

    private const ALLOWED_AUDIO_MIME_TYPES = [
        'audio/webm',
        'audio/ogg',
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/aac',
        'application/octet-stream',
        'video/webm',
        'video/mp4',
    ];

    private const ALLOWED_AUDIO_EXTENSIONS = [
        'webm',
        'ogg',
        'mp3',
        'wav',
        'm4a',
        'mp4',
        'aac',
    ];

    public function __construct(
        private readonly string $uploadsBaseDir
    )
    {
    }

    #[Route('/', name: 'forum_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ForumRepository $repo,
        FormFactoryInterface $formFactory,
        PaginatorInterface $paginator
    ): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $yieldData = new YieldForecastData();
        $profitabilityData = new ProfitabilityAnalysisData();

        $yieldForm = $formFactory->createNamed('yield_forecast', YieldForecastType::class, $yieldData);
        $profitabilityForm = $formFactory->createNamed('profitability', ProfitabilityAnalysisType::class, $profitabilityData);

        if ($request->isMethod('POST')) {
            $yieldForm->handleRequest($request);
            $profitabilityForm->handleRequest($request);
        }

        $toolState = [
            'active_panel' => null,
            'yield' => [
                'result' => '0.00',
                'message' => 'Renseignez les trois champs puis cliquez sur Calculer.',
            ],
            'profitability' => [
                'result' => '0.00',
                'message' => 'Saisissez le revenu et les charges puis cliquez sur Calculer.',
            ],
        ];

        if ($yieldForm->isSubmitted()) {
            $toolState['active_panel'] = 'yield';
            if ($yieldForm->isValid()) {
                $toolState['yield']['result'] = number_format(
                    (float) $yieldData->surface * (float) $yieldData->cropCoefficient * (float) $yieldData->weatherCoefficient,
                    2,
                    '.',
                    ''
                );
                $toolState['yield']['message'] = 'Calcul effectue avec succes.';
            } else {
                $toolState['yield']['message'] = 'Veuillez corriger les erreurs du formulaire.';
            }
        } elseif ($profitabilityForm->isSubmitted()) {
            $toolState['active_panel'] = 'profitability';
            if ($profitabilityForm->isValid()) {
                $profit = (float) $profitabilityData->revenue
                    - ((float) $profitabilityData->fertilizer + (float) $profitabilityData->water + (float) $profitabilityData->labor + (float) $profitabilityData->seeds);
                $toolState['profitability']['result'] = number_format($profit, 2, '.', '');

                if ($profit > 0) {
                    $toolState['profitability']['message'] = 'Exploitation rentable selon les valeurs saisies.';
                } elseif ($profit < 0) {
                    $toolState['profitability']['message'] = 'Le resultat indique une perte selon les valeurs saisies.';
                } else {
                    $toolState['profitability']['message'] = "Le resultat est a l'equilibre.";
                }
            } else {
                $toolState['profitability']['message'] = 'Veuillez corriger les erreurs du formulaire.';
            }
        }

        $forums = $paginator->paginate(
            $repo->createIndexQueryBuilder($search, $sort),
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('Forum/index.html.twig', [
            'forums' => $forums,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
            ],
            'tool_state' => $toolState,
            'yield_form' => $yieldForm->createView(),
            'profitability_form' => $profitabilityForm->createView(),
        ]);
    }

    #[Route('/new', name: 'forum_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $forum = new Forum();
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof User) {
                $forum->setUserId($user->getId());
            }

            try {
                $em->persist($forum);
                $em->flush();

                $this->addFlash('success', 'Sujet cree avec succes.');

                return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
            } catch (\Throwable $exception) {
                $form->addError(new FormError('La creation du sujet a echoue. Verifiez les donnees saisies puis reessayez.'));
                $this->addFlash('danger', 'La creation du sujet a echoue.');
            }
        }

        return $this->render('Forum/new.html.twig', [
            'form' => $form->createView(),
            'form_has_errors' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    #[Route('/{id}', name: 'forum_show', methods: ['GET'])]
    public function show(Forum $forum, MessageRepository $messageRepository): Response
    {
        $messages = $messageRepository->findBy(['forum' => $forum], ['dateEnvoi' => 'ASC']);

        $currentUser = $this->getUser();
        $currentUserId = $currentUser instanceof User ? $currentUser->getId() : null;

        return $this->render('Forum/show.html.twig', [
            'forum' => $forum,
            'messageCount' => count($messages),
            'messages' => array_map(
                function (Message $message) use ($currentUserId): array {
                    $author = $message->getUser();

                    return [
                        'id' => $message->getId(),
                        'content' => $message->getContenu(),
                        'audioPath' => $message->getAudioPath(),
                        'audioUrl' => $message->getAudioPath() !== null
                            ? $this->generateUrl('forum_message_audio', ['filename' => basename($message->getAudioPath())])
                            : null,
                        'translateUrl' => $message->getContenu() !== ''
                            ? $this->generateUrl('forum_message_translate', ['id' => $message->getId()])
                            : null,
                        'transcribeUrl' => $message->getAudioPath() !== null
                            ? $this->generateUrl('forum_message_transcribe', ['id' => $message->getId()])
                            : null,
                        'sentAt' => $message->getDateEnvoi(),
                        'isOwn' => $currentUserId !== null && $author->getId() === $currentUserId,
                        'authorName' => $author->getDisplayName(),
                        'authorInitials' => $author->getInitials(),
                    ];
                },
                $messages
            ),
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'forum_export_pdf', methods: ['GET'])]
    public function exportPdf(Forum $forum, MessageRepository $messageRepository, PdfService $pdfService): Response
    {
        $messages = $messageRepository->findBy(['forum' => $forum], ['dateEnvoi' => 'ASC']);
        $logoDataUri = $pdfService->getImageDataUri('logo_with_text.png');

        $html = $this->renderView('Forum/export_pdf.html.twig', [
            'forum' => $forum,
            'messages' => $messages,
            'messageCount' => count($messages),
            'logoDataUri' => $logoDataUri,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($forum->getTitre())) ?: 'discussion';
        $safeTitle = trim($safeTitle, '-');
        $filename = sprintf('discussion-forum-%d-%s.pdf', $forum->getId(), $safeTitle !== '' ? $safeTitle : 'export');

        return $pdfService->generatePdf($html, $filename, 'A4', 'portrait');
    }

    #[Route('/{id}/messages', name: 'forum_message_create', methods: ['POST'])]
    public function createMessage(
        Request $request,
        Forum $forum,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        SluggerInterface $slugger
    ): Response {
        if (!$this->isCsrfTokenValid('forum_message_' . $forum->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action invalide.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $content = trim((string) $request->request->get('contenu', ''));
        /** @var UploadedFile|null $audioFile */
        $audioFile = $request->files->get('audio_message');

        if ($content === '' && !$audioFile instanceof UploadedFile) {
            $this->addFlash('warning', 'Ajoutez un texte ou un message vocal.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $violations = $validator->validate($content, [
            new Length([
                'max' => 500,
                'maxMessage' => 'Le message ne doit pas depasser {{ limit }} caracteres.',
            ]),
        ]);

        if ($content !== '' && mb_strlen($content) < 2) {
            $this->addFlash('warning', 'Le message doit contenir au moins 2 caracteres.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('warning', $violation->getMessage());
            }

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $audioPath = null;
        if ($audioFile instanceof UploadedFile) {
            try {
                $audioPath = $this->uploadVoiceMessage($audioFile, $slugger);
            } catch (FileException $exception) {
                $this->addFlash('warning', $exception->getMessage());

                return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
            }
        }

        $message = new Message();
        $message->setForum($forum);
        $message->setContenu($content);
        $message->setAudioPath($audioPath);
        $user = $this->getUser();
        if ($user instanceof User) {
            $message->setUser($user);
        }

        $em->persist($message);
        $em->flush();

        $this->addFlash('success', 'Message envoye.');

        return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
    }

    #[Route('/messages/{id}/translate', name: 'forum_message_translate', methods: ['GET'])]
    public function translateMessage(
        Message $message,
        Request $request,
        ForumMessageTranslationService $translationService
    ): JsonResponse {
        if ($message->getContenu() === '') {
            return $this->json([
                'success' => false,
                'error' => 'Seuls les messages texte peuvent etre traduits.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $targetLanguage = (string) $request->query->get('target', '');
        try {
            $translation = $translationService->translate($message->getContenu(), $targetLanguage);
        } catch (ForumMessageTranslationException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return $this->json([
            'success' => true,
            'translation' => $translation['translation'],
            'target' => $translation['target'],
            'targetLabel' => $translation['targetLabel'],
            'source' => $translation['source'],
        ]);
    }

    #[Route('/messages/{id}/transcribe', name: 'forum_message_transcribe', methods: ['GET'])]
    public function transcribeMessage(Message $message, ForumVoiceTranscriptionService $transcriptionService): JsonResponse
    {
        $audioPath = $message->getAudioPath();
        if ($audioPath === null) {
            return $this->json([
                'success' => false,
                'error' => 'Ce message ne contient pas de vocal.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $absolutePath = $this->uploadsBaseDir . '/' . ltrim($audioPath, '/');
        $mimeType = $this->guessAudioMimeType($absolutePath);

        try {
            $transcript = $transcriptionService->transcribe($absolutePath, $mimeType);
        } catch (ForumMessageTranslationException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return $this->json([
            'success' => true,
            'transcript' => $transcript,
        ]);
    }

    #[Route('/ai-assistant', name: 'forum_ai_assistant', methods: ['POST'])]
    public function aiAssistant(Request $request, ForumAiAssistantService $assistantService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'success' => false,
                'error' => 'Requete invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['_token'] ?? $request->headers->get('X-CSRF-TOKEN', ''));
        if (!$this->isCsrfTokenValid('forum_ai_assistant', $token)) {
            return $this->json([
                'success' => false,
                'error' => 'Action invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $question = trim((string) ($payload['message'] ?? ''));

        try {
            $answer = $assistantService->ask($question);
        } catch (ForumAiAssistantException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return $this->json([
            'success' => true,
            'answer' => $answer,
        ]);
    }

    #[Route('/{id}/edit', name: 'forum_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', 'Sujet mis a jour.');

                return $this->redirectToRoute('forum_index');
            } catch (\Throwable $exception) {
                $form->addError(new FormError('La mise a jour du sujet a echoue. Verifiez les donnees saisies puis reessayez.'));
                $this->addFlash('danger', 'La mise a jour du sujet a echoue.');
            }
        }

        return $this->render('Forum/edit.html.twig', [
            'forum' => $forum,
            'form' => $form->createView(),
            'form_has_errors' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    #[Route('/{id}/delete', name: 'forum_delete', methods: ['POST'])]
    public function delete(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $forum->getId(), $request->request->get('_token'))) {
            $em->remove($forum);
            $em->flush();
            $this->addFlash('success', 'Sujet supprime.');
        }

        return $this->redirectToRoute('forum_index');
    }

    #[Route('/messages/audio/{filename}', name: 'forum_message_audio', methods: ['GET'])]
    public function audio(string $filename): Response
    {
        $safeFilename = basename($filename);
        $path = $this->uploadsBaseDir . '/forum-voices/' . $safeFilename;

        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier audio introuvable.');
        }

        return new BinaryFileResponse($path);
    }

    private function uploadVoiceMessage(UploadedFile $audioFile, SluggerInterface $slugger): string
    {
        if (($audioFile->getSize() ?? 0) > self::MAX_AUDIO_SIZE) {
            throw new FileException('Le message vocal est trop volumineux (max 10 Mo).');
        }

        $mimeType = $audioFile->getMimeType() ?? '';
        $guessedExtension = strtolower((string) ($audioFile->guessExtension() ?: ''));
        $clientExtension = strtolower((string) $audioFile->getClientOriginalExtension());
        $hasAllowedMimeType = in_array($mimeType, self::ALLOWED_AUDIO_MIME_TYPES, true);
        $hasAllowedExtension = in_array($guessedExtension, self::ALLOWED_AUDIO_EXTENSIONS, true)
            || in_array($clientExtension, self::ALLOWED_AUDIO_EXTENSIONS, true);

        if (!$hasAllowedMimeType && !$hasAllowedExtension) {
            throw new FileException('Format audio non autorise.');
        }

        $targetDirectory = $this->uploadsBaseDir . '/forum-voices';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new FileException('Impossible de preparer le dossier des messages vocaux.');
        }

        $originalFilename = pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalFilename !== '' ? $originalFilename : 'voice-message');
        $extension = $guessedExtension !== ''
            ? $guessedExtension
            : ($clientExtension !== '' ? $clientExtension : 'webm');
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

        try {
            $audioFile->move($targetDirectory, $newFilename);
        } catch (FileException $exception) {
            throw new FileException('Erreur lors de l\'upload du message vocal.');
        }

        return 'forum-voices/' . $newFilename;
    }

    private function guessAudioMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $detectedMimeType = @mime_content_type($path);

        if (is_string($detectedMimeType) && $detectedMimeType !== '') {
            if ($detectedMimeType === 'video/webm' && $extension === 'webm') {
                return 'audio/webm';
            }

            if ($detectedMimeType === 'video/mp4' && in_array($extension, ['m4a', 'mp4'], true)) {
                return 'audio/mp4';
            }

            if (str_starts_with($detectedMimeType, 'audio/')) {
                return $detectedMimeType;
            }
        }

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a', 'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            'webm' => 'audio/webm',
            default => 'audio/webm',
        };
    }
}
