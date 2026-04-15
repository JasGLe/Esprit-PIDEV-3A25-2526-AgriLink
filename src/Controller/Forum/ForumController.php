<?php

namespace App\Controller\Forum;

use App\Dto\Forum\CropRecommendationData;
use App\Dto\Forum\ProfitabilityAnalysisData;
use App\Dto\Forum\YieldForecastData;
use App\Entity\Forum\Forum;
use App\Entity\Forum\Message;
use App\Entity\UserManagement\User;
use App\Form\Forum\CropRecommendationType;
use App\Form\Forum\ForumType;
use App\Form\Forum\ProfitabilityAnalysisType;
use App\Form\Forum\YieldForecastType;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/forum')]
class ForumController extends AbstractController
{
    #[Route('/', name: 'forum_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ForumRepository $repo, FormFactoryInterface $formFactory): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $yieldData = new YieldForecastData();
        $recommendationData = new CropRecommendationData();
        $profitabilityData = new ProfitabilityAnalysisData();

        $yieldForm = $formFactory->createNamed('yield_forecast', YieldForecastType::class, $yieldData);
        $recommendationForm = $formFactory->createNamed('crop_recommendation', CropRecommendationType::class, $recommendationData);
        $profitabilityForm = $formFactory->createNamed('profitability', ProfitabilityAnalysisType::class, $profitabilityData);

        if ($request->isMethod('POST') && $request->request->has($yieldForm->getName())) {
            $yieldForm->handleRequest($request);
        }
        if ($request->isMethod('POST') && $request->request->has($recommendationForm->getName())) {
            $recommendationForm->handleRequest($request);
        }
        if ($request->isMethod('POST') && $request->request->has($profitabilityForm->getName())) {
            $profitabilityForm->handleRequest($request);
        }

        $toolState = [
            'active_panel' => null,
            'yield' => [
                'result' => '0.00',
                'message' => 'Renseignez les trois champs puis cliquez sur Calculer.',
            ],
            'recommendation' => [
                'result' => 'Aucune',
                'message' => 'Selectionnez un sol et une saison puis cliquez sur Recommander.',
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
        } elseif ($recommendationForm->isSubmitted()) {
            $toolState['active_panel'] = 'recommendation';
            if ($recommendationForm->isValid()) {
                if ($recommendationData->soil === 'sableux' && $recommendationData->season === 'ete') {
                    $toolState['recommendation']['result'] = 'Pasteque';
                    $toolState['recommendation']['message'] = 'Regle appliquee avec succes.';
                } elseif ($recommendationData->soil === 'argileux' && $recommendationData->season === 'hiver') {
                    $toolState['recommendation']['result'] = 'Ble';
                    $toolState['recommendation']['message'] = 'Regle appliquee avec succes.';
                } else {
                    $toolState['recommendation']['result'] = 'Aucune recommandation';
                    $toolState['recommendation']['message'] = 'Aucune regle ne correspond a cette combinaison pour le moment.';
                }
            } else {
                $toolState['recommendation']['message'] = 'Veuillez corriger les erreurs du formulaire.';
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

        return $this->render('Forum/index.html.twig', [
            'forums' => $repo->findForIndex($search, $sort),
            'filters' => [
                'q' => $search,
                'sort' => $sort,
            ],
            'tool_state' => $toolState,
            'yield_form' => $yieldForm->createView(),
            'recommendation_form' => $recommendationForm->createView(),
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

            $forum->setDateCreation(new \DateTimeImmutable());

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
                static function (Message $message) use ($currentUserId): array {
                    $author = $message->getUser();

                    return [
                        'id' => $message->getId(),
                        'content' => $message->getContenu(),
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

    #[Route('/{id}/messages', name: 'forum_message_create', methods: ['POST'])]
    public function createMessage(Request $request, Forum $forum, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('forum_message_' . $forum->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action invalide.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $content = trim((string) $request->request->get('contenu', ''));
        $violations = $validator->validate($content, [
            new NotBlank(['message' => 'Le message ne peut pas etre vide.']),
            new Length([
                'min' => 2,
                'max' => 500,
                'minMessage' => 'Le message doit contenir au moins {{ limit }} caracteres.',
                'maxMessage' => 'Le message ne doit pas depasser {{ limit }} caracteres.',
            ]),
        ]);

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('warning', $violation->getMessage());
            }

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $message = new Message();
        $message->setForum($forum);
        $message->setContenu($content);
        $message->setDateEnvoi(new \DateTimeImmutable());

        $user = $this->getUser();
        if ($user instanceof User) {
            $message->setUser($user);
        }

        $em->persist($message);
        $em->flush();

        $this->addFlash('success', 'Message envoye.');

        return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
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

    /**
     * @return string[]
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin instanceof FormInterface ? $origin->getName() : null;
            $messages[] = $name && $name !== $form->getName()
                ? sprintf('%s: %s', ucfirst($name), $error->getMessage())
                : $error->getMessage();
        }

        return array_values(array_unique($messages));
    }

}
