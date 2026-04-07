<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\Message;
use App\Entity\UserManagement\User;
use App\Form\Forum\ForumType;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route('/', name: 'forum_index', methods: ['GET'])]
    public function index(Request $request, ForumRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');

        return $this->render('Forum/index.html.twig', [
            'forums' => $repo->findForIndex($search, $sort),
            'filters' => [
                'q' => $search,
                'sort' => $sort,
            ],
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

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $error) {
                $this->addFlash('warning', $error);
            }
            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire avant de continuer.');
        }

        return $this->render('Forum/new.html.twig', [
            'form' => $form->createView(),
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

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $error) {
                $this->addFlash('warning', $error);
            }
            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire avant de continuer.');
        }

        return $this->render('Forum/edit.html.twig', [
            'forum' => $forum,
            'form' => $form->createView(),
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
