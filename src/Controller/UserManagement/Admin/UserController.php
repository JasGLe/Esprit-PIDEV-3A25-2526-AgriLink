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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $search = trim($request->query->getString('search', ''));
        $roleFilter = trim($request->query->getString('role', ''));
        $statusFilter = trim($request->query->getString('status', ''));
        $orderBy = $request->query->getString('orderBy', 'createdAt');
        $orderDir = strtoupper($request->query->getString('orderDir', 'DESC'));

        // Convert status filter to boolean
        $activeFilter = null;
        if ($statusFilter === 'active') {
            $activeFilter = true;
        } elseif ($statusFilter === 'inactive') {
            $activeFilter = false;
        }

        // Validate and clean role filter
        $validRoles = ['ADMIN', 'AGRICULTEUR', 'AGRIPLUS', 'FOURNISSEUR', 'USER'];
        $roleFilterValue = null;
        if ($roleFilter !== '' && in_array($roleFilter, $validRoles, true)) {
            $roleFilterValue = $roleFilter;
        }

        $allowedOrderBy = ['createdAt', 'lastLogin', 'email', 'nom', 'role'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'createdAt';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $result = $this->userRepository->findPaginated(
            $page,
            $limit,
            $roleFilterValue,
            $activeFilter,
            $search !== '' ? $search : null,
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
            'roleFilter' => $roleFilterValue,
            'statusFilter' => $statusFilter !== '' ? $statusFilter : null,
            'orderBy' => $orderBy,
            'orderDir' => $orderDir,
            'stats' => $stats,
            'validRoles' => $validRoles,
        ]);
    }

    #[Route('/search', name: 'search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $search = trim($request->query->getString('search', ''));
        $roleFilter = trim($request->query->getString('role', ''));
        $statusFilter = trim($request->query->getString('status', ''));
        $orderBy = $request->query->getString('orderBy', 'createdAt');
        $orderDir = strtoupper($request->query->getString('orderDir', 'DESC'));

        // Convert status filter to boolean
        $activeFilter = null;
        if ($statusFilter === 'active') {
            $activeFilter = true;
        } elseif ($statusFilter === 'inactive') {
            $activeFilter = false;
        }

        // Validate and clean role filter
        $validRoles = ['ADMIN', 'AGRICULTEUR', 'AGRIPLUS', 'FOURNISSEUR', 'USER'];
        $roleFilterValue = null;
        if ($roleFilter !== '' && \in_array($roleFilter, $validRoles, true)) {
            $roleFilterValue = $roleFilter;
        }

        $allowedOrderBy = ['createdAt', 'lastLogin', 'email', 'nom', 'role'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'createdAt';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $result = $this->userRepository->findPaginated(
            $page,
            $limit,
            $roleFilterValue,
            $activeFilter,
            $search !== '' ? $search : null,
            $orderBy,
            $orderDir
        );

        $totalPages = (int) \ceil($result['total'] / $limit);

        return new JsonResponse([
            'success' => true,
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'users' => array_map(function($user) {
                return [
                    'id' => $user->getId(),
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole(),
                    'isActive' => $user->isActive(),
                    'isBanned' => $user->isBanned(),
                    'emailVerified' => $user->isEmailVerified(),
                    'createdAt' => $user->getCreatedAt()->format('d/m/Y'),
                    'lastLogin' => $user->getLastLogin()?->format('d/m/Y'),
                    'initials' => $user->getInitials(),
                    'photoProfil' => $user->getPhotoProfil(),
                ];
            }, $result['data']),
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

    #[Route('/{id}/upload-photo', name: 'upload_photo', methods: ['POST'])]
    public function uploadPhoto(User $user, Request $request, FileUploader $fileUploader): JsonResponse
    {
        $adminUserData = $request->request->all()['admin_user'] ?? [];
        $csrfToken = $adminUserData['_token'] ?? null;

        if (!$this->isCsrfTokenValid('admin_user', is_string($csrfToken) ? $csrfToken : null)) {
            return $this->json(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
        }

        $filesData = $request->files->all()['admin_user'] ?? [];
        $photoFile = $filesData['profilePhoto'] ?? null;

        if (!$photoFile) {
            return $this->json(['success' => false, 'message' => 'Aucun fichier sélectionné.'], 400);
        }

        try {
            $oldPhoto = $user->getPhotoProfil();
            $photoFilename = $fileUploader->upload($photoFile, 'profiles', $oldPhoto);
        } catch (FileException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        $user->setPhotoProfil($photoFilename);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'photoUrl' => '/agrilink/uploads/' . ltrim($photoFilename, '/'),
            'photoFilename' => $photoFilename,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user, Request $request): Response
    {
        // CSRF protection
        $submittedToken = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('toggle-status-' . $user->getId(), $submittedToken !== '' ? $submittedToken : null)) {
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
        $submittedToken = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $submittedToken !== '' ? $submittedToken : null)) {
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
        $expectsJson = $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json');

        // CSRF protection
        $submittedToken = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('delete-photo-' . $user->getId(), $submittedToken !== '' ? $submittedToken : null)) {
            if ($expectsJson) {
                return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
            }

            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
        }

        if ($user->getPhotoProfil()) {
            // Delete the file using FileUploader service (handles all platform paths correctly)
            $fileUploader->deleteFile($user->getPhotoProfil());
            
            // Clear the photo reference in the database
            $user->setPhotoProfil(null);
            $this->entityManager->flush();

            if ($expectsJson) {
                return $this->json(['success' => true]);
            }

            $this->addFlash('success', 'Photo de profil supprimée avec succès.');
        } else {
            if ($expectsJson) {
                return $this->json(['success' => false, 'message' => 'Aucune photo de profil à supprimer.'], 400);
            }

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
        $submittedTokenString = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('ban-user-' . $user->getId(), $submittedTokenString !== '' ? $submittedTokenString : null)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas bannir votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        $reason = $request->request->getString('ban_reason', 'Violation des conditions d\'utilisation');
        $user->ban($reason);
        $this->entityManager->flush();

        $securityEventService->logAccountBanned($user, $reason);

        // Send ban notification email
        try {
            $toEmail = $user->getEmail();
            if ($toEmail === null || $toEmail === '') {
                throw new \RuntimeException('Missing user email');
            }
            $htmlContent = $twig->render('emails/account_banned.html.twig', [
                'user' => $user,
                'reason' => $reason,
                'bannedAt' => $user->getBannedAt(),
                'appUrl' => $appUrl,
            ]);

            $email = (new Email())
                ->from('noreply@agrilink.com')
                ->to($toEmail)
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
        $submittedToken = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('unban-user-' . $user->getId(), $submittedToken !== '' ? $submittedToken : null)) {
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
