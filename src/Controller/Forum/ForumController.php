<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Message;
use App\Entity\UserManagement\User;
use App\Form\Forum\ForumType;
use App\Repository\Forum\ForumRepository;
use App\Repository\MessageRepository;
use App\Repository\UserManagement\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum')]
class ForumController extends AbstractController
{
    #[Route('/', name: 'forum_index', methods: ['GET'])]
    public function index(ForumRepository $repo): Response
    {
        return $this->render('Forum/index.html.twig', [
            'forums' => $repo->findBy([], ['dateCreation' => 'DESC']),
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

            $em->persist($forum);
            $em->flush();

            $this->addFlash('success', 'Sujet cree avec succes.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        return $this->render('Forum/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'forum_show', methods: ['GET'])]
    public function show(Forum $forum, MessageRepository $messageRepository, UserRepository $userRepository): Response
    {
        $messages = $messageRepository->findBy(['forumId' => $forum->getId()], ['dateEnvoi' => 'ASC']);
        $userIds = array_values(array_unique(array_filter(
            array_map(static fn (Message $message): ?int => $message->getUserId(), $messages),
            static fn (?int $userId): bool => $userId !== null
        )));

        $usersById = [];
        if ($userIds !== []) {
            foreach ($userRepository->findBy(['id' => $userIds]) as $user) {
                $usersById[$user->getId()] = $user;
            }
        }

        $currentUser = $this->getUser();
        $currentUserId = $currentUser instanceof User ? $currentUser->getId() : null;

        return $this->render('Forum/show.html.twig', [
            'forum' => $forum,
            'messages' => array_map(
                static function (Message $message) use ($usersById, $currentUserId): array {
                    $author = $message->getUserId() !== null ? ($usersById[$message->getUserId()] ?? null) : null;

                    return [
                        'id' => $message->getId(),
                        'content' => $message->getContenu(),
                        'sentAt' => $message->getDateEnvoi(),
                        'isOwn' => $currentUserId !== null && $message->getUserId() === $currentUserId,
                        'authorName' => $author instanceof User ? $author->getDisplayName() : 'Utilisateur',
                        'authorInitials' => $author instanceof User ? $author->getInitials() : 'U',
                    ];
                },
                $messages
            ),
        ]);
    }

    #[Route('/{id}/messages', name: 'forum_message_create', methods: ['POST'])]
    public function createMessage(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('forum_message_' . $forum->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action invalide.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $content = trim((string) $request->request->get('contenu', ''));
        if ($content === '') {
            $this->addFlash('warning', 'Le message ne peut pas etre vide.');

            return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
        }

        $message = new Message();
        $message->setForumId($forum->getId());
        $message->setContenu($content);
        $message->setDateEnvoi(new \DateTimeImmutable());

        $user = $this->getUser();
        if ($user instanceof User) {
            $message->setUserId($user->getId());
        }

        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('forum_show', ['id' => $forum->getId()]);
    }

    #[Route('/{id}/edit', name: 'forum_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Sujet mis a jour.');

            return $this->redirectToRoute('forum_index');
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
}
