<?php

namespace App\Controller\UserManagement\Admin;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\AdminUserType;
use App\Repository\UserManagement\UserRepository;
use App\Service\FileUploader;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role');
        $statusFilter = $request->query->get('status');
        $orderBy = $request->query->get('orderBy', 'createdAt');
        $orderDir = $request->query->get('orderDir', 'DESC');

        // Convert status filter to boolean
        $activeFilter = null;
        if ($statusFilter === 'active') {
            $activeFilter = true;
        } elseif ($statusFilter === 'inactive') {
            $activeFilter = false;
        }

        // Validate and clean role filter
        $validRoles = ['ADMIN', 'AGRICULTEUR', 'AGRIPLUS', 'FOURNISSEUR', 'USER'];
        if (!$roleFilter || !in_array($roleFilter, $validRoles)) {
            $roleFilter = null;
        }

        $result = $this->userRepository->findPaginated(
            $page,
            $limit,
            $roleFilter,
            $activeFilter,
            $search ?: null,
            $orderBy,
            $orderDir
        );

        $totalPages = (int) ceil($result['total'] / $limit);

        // Get statistics for the dashboard cards
        $stats = $this->userRepository->getStatistics();

        return $this->render('user_management/admin/users/list.html.twig', [
            'users' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'orderBy' => $orderBy,
            'orderDir' => $orderDir,
            'stats' => $stats,
            'validRoles' => $validRoles,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, FileUploader $fileUploader): Response
    {
        $user = new User();
        
        $form = $this->createForm(AdminUserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle profile photo upload
            $photoFile = $form->get('profilePhoto')->getData();
            if ($photoFile) {
                $photoFilename = $fileUploader->upload($photoFile, 'profiles');
                $user->setPhotoProfil($photoFilename);
            }

            // Set email as verified by admin
            $user->setEmailVerified(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');

            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('user_management/admin/users/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, FileUploader $fileUploader): Response
    {
        $form = $this->createForm(AdminUserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle profile photo upload
            $photoFile = $form->get('profilePhoto')->getData();
            if ($photoFile) {
                $oldPhoto = $user->getPhotoProfil();
                $photoFilename = $fileUploader->upload($photoFile, 'profiles', $oldPhoto);
                $user->setPhotoProfil($photoFilename);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');

            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('user_management/admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user, Request $request): Response
    {
        // CSRF protection
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle-status-' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Prevent admin from deactivating themselves
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre statut.');
            return $this->redirectToRoute('admin_users_list');
        }

        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();

        $status = $user->isActive() ? 'activé' : 'suspendu';
        $this->addFlash('success', sprintf('Utilisateur %s avec succès.', $status));

        return $this->redirectToRoute('admin_users_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(User $user, Request $request): Response
    {
        // CSRF protection
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Prevent admin from deleting themselves
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Nullify exploitation references (no onDelete cascade on that FK)
        foreach ($user->getExploitations() as $exploitation) {
            $exploitation->setUser(null);
        }

        $userName = $user->getDisplayName();
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Utilisateur "%s" supprimé définitivement.', $userName));

        return $this->redirectToRoute('admin_users_list');
    }

    #[Route('/{id}/delete-photo', name: 'delete_photo', methods: ['POST'])]
    public function deletePhoto(User $user, Request $request, FileUploader $fileUploader): Response
    {
        // CSRF protection
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-photo-' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
        }

        if ($user->getPhotoProfil()) {
            // Delete the file using FileUploader service (handles all platform paths correctly)
            $fileUploader->deleteFile($user->getPhotoProfil());
            
            // Clear the photo reference in the database
            $user->setPhotoProfil(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'Photo de profil supprimée avec succès.');
        } else {
            $this->addFlash('info', 'Aucune photo de profil à supprimer.');
        }

        return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/ban', name: 'ban', methods: ['POST'])]
    public function ban(
        User $user,
        Request $request,
        SecurityEventService $securityEventService,
        MailerInterface $mailer,
        Environment $twig,
        #[Autowire('%env(APP_URL)%')]
        string $appUrl
    ): Response {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('ban-user-' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas bannir votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        $reason = $request->request->get('ban_reason', 'Violation des conditions d\'utilisation');
        $user->ban($reason);
        $this->entityManager->flush();

        $securityEventService->logAccountBanned($user, $reason);

        // Send ban notification email
        try {
            $htmlContent = $twig->render('emails/account_banned.html.twig', [
                'user' => $user,
                'reason' => $reason,
                'bannedAt' => $user->getBannedAt(),
                'appUrl' => $appUrl,
            ]);

            $email = (new Email())
                ->from('noreply@agrilink.com')
                ->to($user->getEmail())
                ->subject('AgriLink - Votre compte a été suspendu')
                ->html($htmlContent);

            $mailer->send($email);
        } catch (\Exception $e) {
        }

        $this->addFlash('success', sprintf('L\'utilisateur "%s" a été banni.', $user->getDisplayName()));
        return $this->redirectToRoute('admin_users_list');
    }

    #[Route('/{id}/unban', name: 'unban', methods: ['POST'])]
    public function unban(
        User $user,
        Request $request,
        SecurityEventService $securityEventService
    ): Response {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('unban-user-' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        $user->unban();
        $this->entityManager->flush();

        $securityEventService->logAccountUnbanned($user);

        $this->addFlash('success', sprintf('L\'utilisateur "%s" a été débanni.', $user->getDisplayName()));
        return $this->redirectToRoute('admin_users_list');
    }
}

