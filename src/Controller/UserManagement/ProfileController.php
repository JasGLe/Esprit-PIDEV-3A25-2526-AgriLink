<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\ChangePasswordType;
use App\Form\UserManagement\ProfileEditFormType;
use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserSessionRepository;
use App\Service\AvatarService;
use App\Service\BackupCodeService;
use App\Service\EmailVerificationService;
use App\Service\FaceRecognitionService;
use App\Service\FileUploader;
use App\Service\SecurityEventService;
use App\Service\UserGamificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function view(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user_management/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        Security $security,
        EmailVerificationService $emailVerificationService,
        SecurityEventService $securityEventService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $originalEmail = $user->getEmail() ?? '';

        // Handle photo-only upload
        if ($request->isMethod('POST') && $request->request->get('photo_only')) {
            $csrf = $request->request->getString('_token', '');
            if ($this->isCsrfTokenValid('profile_photo_upload', $csrf !== '' ? $csrf : null)) {
                $profilePhotoFile = $request->files->get('profile_edit_form')['profilePhoto'] ?? null;

                if ($profilePhotoFile) {
                    try {
                        $profilePhotoPath = $fileUploader->upload(
                            $profilePhotoFile,
                            'profiles',
                            $user->getPhotoProfil()
                        );
                        $user->setPhotoProfil($profilePhotoPath);
                        $entityManager->flush();

                        $entityManager->refresh($user);
                        $token = $security->getToken();
                        if ($token) {
                            $token->setUser($user);
                        }

                        $this->addFlash('success', 'Votre photo de profil a été mise à jour avec succès.');
                    } catch (\Exception $e) {
                        $this->addFlash('error', $e->getMessage());
                    }
                } else {
                    $this->addFlash('warning', 'Veuillez sélectionner une photo.');
                }

                return $this->redirectToRoute('app_profile_edit');
            }
        }

        // Handle full profile form
        $form = $this->createForm(ProfileEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Feature 4: Detect email change
            $newEmail = $user->getEmail();
            if ($newEmail !== null && $newEmail !== $originalEmail) {
                // Store new email as pending, revert current email
                $user->setPendingEmail($newEmail);
                $user->setEmail($originalEmail);

                // Generate verification code and send to NEW email
                $code = $emailVerificationService->generateVerificationCode($user);
                try {
                    $emailVerificationService->sendVerificationEmailTo($user, $newEmail, $code);
                    $this->addFlash('info', 'Un code de vérification a été envoyé à ' . $newEmail . '. Votre email ne sera modifié qu\'après vérification.');
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Impossible d\'envoyer le code de vérification. Veuillez réessayer.');
                    $user->setPendingEmail(null);
                }

                $securityEventService->logEmailChangeRequested($user, $newEmail);
            }

            // Handle photo upload
            $profilePhotoFile = $form->get('profilePhoto')->getData();

            if ($profilePhotoFile) {
                try {
                    $profilePhotoPath = $fileUploader->upload(
                        $profilePhotoFile,
                        'profiles',
                        $user->getPhotoProfil()
                    );
                    $user->setPhotoProfil($profilePhotoPath);
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('app_profile_edit');
                }
            }

            $entityManager->flush();

            $entityManager->refresh($user);
            $token = $security->getToken();
            if ($token) {
                $token->setUser($user);
            }

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user_management/profile/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        SecurityEventService $securityEventService,
        #[Autowire('%env(APP_URL)%')] string $appUrl
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!is_string($currentPassword) || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            $newPassword = $form->get('newPassword')->getData();
            if (!is_string($newPassword)) {
                $this->addFlash('error', 'Le nouveau mot de passe est invalide.');
                return $this->redirectToRoute('app_profile_change_password');
            }
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $securityEventService->logPasswordChanged($user);

            try {
                $toEmail = $user->getEmail();
                if ($toEmail === null || $toEmail === '') {
                    throw new \RuntimeException('Missing user email');
                }
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@agrilink.com', 'AgriLink'))
                    ->to($toEmail)
                    ->subject('Votre mot de passe AgriLink a été modifié')
                    ->htmlTemplate('emails/password_changed.html.twig')
                    ->context([
                        'user' => $user,
                        'date' => new \DateTime(),
                        'appUrl' => $appUrl,
                    ]);

                $mailer->send($email);
            } catch (\Exception $e) {
            }

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user_management/profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/security', name: 'app_profile_security', methods: ['GET'])]
    public function securitySettings(
        SecurityEventRepository $securityEventRepository,
        BackupCodeService $backupCodeService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $recentEvents = $securityEventRepository->findByUser((int) $user->getId(), 8);
        $backupCodesRemaining = $backupCodeService->getRemainingCount($user);
        
        // Get face recognition status
        $faceStatus = [
            'enrolled' => !empty($user->getFaceDescriptor()),
        ];

        return $this->render('user_management/profile/security.html.twig', [
            'user'                 => $user,
            'recentEvents'         => $recentEvents,
            'backupCodesRemaining' => $backupCodesRemaining,
            'faceStatus'           => $faceStatus,
        ]);
    }

    #[Route('/security/toggle-2fa', name: 'app_profile_toggle_2fa', methods: ['POST'])]
    public function toggle2fa(
        Request $request,
        EntityManagerInterface $entityManager,
        BackupCodeService $backupCodeService,
        UserGamificationService $gamificationService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('toggle_2fa', $csrf !== '' ? $csrf : null)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_security');
        }

        $enabled = !$user->isTwoFactorEnabled();
        $user->setTwoFactorEnabled($enabled);

        if ($enabled) {
            // Auto-generate backup codes when enabling 2FA
            $plainCodes = $backupCodeService->generate($user);
            $request->getSession()->set('_backup_codes_display', $plainCodes);
            $entityManager->flush();
            $gamificationService->recalculate($user);

            $this->addFlash(
                'success',
                'La double authentification (2FA) a été activée. Sauvegardez vos codes de secours !'
            );

            return $this->redirectToRoute('app_profile_backup_codes');
        }

        $entityManager->flush();
        $gamificationService->recalculate($user);

        $this->addFlash('success', 'La double authentification (2FA) a été désactivée.');

        return $this->redirectToRoute('app_profile_security');
    }

    // Feature 3: Logout all devices
    #[Route('/security/revoke-all-sessions', name: 'app_profile_revoke_all_sessions', methods: ['POST'])]
    public function revokeAllSessions(
        Request $request,
        UserSessionRepository $userSessionRepository,
        SecurityEventService $securityEventService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('revoke_all_sessions', $csrf !== '' ? $csrf : null)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_security');
        }

        $count = $userSessionRepository->revokeAllForUser((int) $user->getId());
        $securityEventService->logSessionRevoked($user);

        $this->addFlash('success', sprintf('%d session(s) ont été révoquées. Vous devrez vous reconnecter sur vos autres appareils.', $count));

        return $this->redirectToRoute('app_profile_security');
    }

    // Feature 5: Login history
    #[Route('/login-history', name: 'app_profile_login_history', methods: ['GET'])]
    public function loginHistory(
        SecurityEventRepository $securityEventRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $events = $securityEventRepository->findLoginHistoryByUser((int) $user->getId());

        return $this->render('user_management/profile/login_history.html.twig', [
            'user' => $user,
            'events' => $events,
        ]);
    }

    // Feature 7: Backup codes
    #[Route('/security/backup-codes', name: 'app_profile_backup_codes', methods: ['GET'])]
    public function backupCodes(
        Request $request,
        BackupCodeService $backupCodeService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Show plain codes only once (from session, set during generation)
        $plainCodes = $request->getSession()->get('_backup_codes_display');
        $request->getSession()->remove('_backup_codes_display');

        $remainingCount = $backupCodeService->getRemainingCount($user);

        return $this->render('user_management/profile/backup_codes.html.twig', [
            'user' => $user,
            'plainCodes' => $plainCodes,
            'remainingCount' => $remainingCount,
        ]);
    }

    #[Route('/security/regenerate-backup-codes', name: 'app_profile_regenerate_backup_codes', methods: ['POST'])]
    public function regenerateBackupCodes(
        Request $request,
        BackupCodeService $backupCodeService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('regenerate_backup_codes', $csrf !== '' ? $csrf : null)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_security');
        }

        $plainCodes = $backupCodeService->generate($user);
        $request->getSession()->set('_backup_codes_display', $plainCodes);

        $this->addFlash('success', 'De nouveaux codes de secours ont été générés. Les anciens codes sont invalides.');

        return $this->redirectToRoute('app_profile_backup_codes');
    }

    #[Route('/delete-photo', name: 'app_profile_delete_photo', methods: ['POST'])]
    public function deletePhoto(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader
    ): Response {
        $token = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('profile_photo_delete', $token !== '' ? $token : null)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_profile_edit');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getPhotoProfil()) {
            try {
                $fileUploader->deleteFile($user->getPhotoProfil());
                $user->setPhotoProfil(null);
                $entityManager->flush();

                $this->addFlash('success', 'Votre photo de profil a été supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la photo.');
            }
        }

        return $this->redirectToRoute('app_profile_edit');
    }

    #[Route('/face/status', name: 'app_profile_face_status', methods: ['GET'])]
    public function getFaceStatus(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'enrolled' => !empty($user->getFaceDescriptor()),
            'enrolled_at' => $user->getFaceEnrolledAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/face/enroll', name: 'app_profile_face_enroll', methods: ['POST'])]
    public function enrollFace(
        Request $request,
        EntityManagerInterface $entityManager,
        FaceRecognitionService $faceRecognitionService,
        UserPasswordHasherInterface $passwordHasher,
        UserGamificationService $gamificationService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('face_enroll', $csrf !== '' ? $csrf : null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        // Skip password validation for OAuth users (they don't have passwords)
        if (!$user->getOauthProvider()) {
            $password = $request->request->getString('password', '');
            if ($password === '' || !$passwordHasher->isPasswordValid($user, $password)) {
                return new JsonResponse(['error' => 'Mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $descriptor = $request->request->getString('descriptor', '');
        if ($descriptor === '') {
            return new JsonResponse(['error' => 'Face descriptor manquant.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $descriptorArray = json_decode($descriptor, true);
            if (!is_array($descriptorArray)) {
                return new JsonResponse(['error' => 'Format de descriptor invalide.'], Response::HTTP_BAD_REQUEST);
            }

            // Empty array = password-validation probe from the widget; do not store anything.
            if (empty($descriptorArray)) {
                return new JsonResponse(['success' => true, 'password_only' => true]);
            }

            $normalizedDescriptor = $this->normalizeNumericList($descriptorArray, 128);
            if ($normalizedDescriptor === null) {
                return new JsonResponse(['error' => 'Descriptor invalide (128 valeurs attendues).'], Response::HTTP_BAD_REQUEST);
            }

            $storedDescriptor = $faceRecognitionService->storeFaceDescriptor($normalizedDescriptor);
            $user->setFaceDescriptor($storedDescriptor);
            $user->setFaceEnrolledAt(new \DateTime());

            $entityManager->flush();
            $gamificationService->recalculate($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Votre visage a été enregistré avec succès.',
                'enrolled_at' => $user->getFaceEnrolledAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'enregistrement du visage: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/face/remove', name: 'app_profile_face_remove', methods: ['POST'])]
    public function removeFace(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserGamificationService $gamificationService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('face_remove', $csrf !== '' ? $csrf : null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        // Skip password validation for OAuth users (they don't have passwords)
        if (!$user->getOauthProvider()) {
            $password = $request->request->getString('password', '');
            if ($password === '' || !$passwordHasher->isPasswordValid($user, $password)) {
                return new JsonResponse(['error' => 'Mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
            }
        }

        try {
            $user->setFaceDescriptor(null);
            $user->setFaceEnrolledAt(null);
            $entityManager->flush();
            $gamificationService->recalculate($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Votre face ID a été supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/security/toggle-intrusion-capture', name: 'app_profile_toggle_intrusion_capture', methods: ['POST'])]
    public function toggleIntrusionCapture(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('toggle_intrusion_capture', $csrf !== '' ? $csrf : null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $enabled = $request->request->getBoolean('enabled');
            $user->setIntrusionCaptureEnabled($enabled);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => $enabled 
                    ? 'Alertes de sécurité activées.' 
                    : 'Alertes de sécurité désactivées.',
                'enabled' => $user->isIntrusionCaptureEnabled(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/generate-avatar', name: 'app_profile_generate_avatar', methods: ['POST'])]
    public function generateAvatar(
        Request $request,
        AvatarService $avatarService,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        Security $security,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $csrf = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('generate_avatar', $csrf !== '' ? $csrf : null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        if (!$user->getPhotoProfil()) {
            return new JsonResponse(
                ['error' => 'Aucune photo de profil trouvée. Veuillez d\'abord télécharger une photo.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$avatarService->isConfigured()) {
            return new JsonResponse(
                ['error' => 'Le service d\'avatar n\'est pas configuré. ' . $avatarService->getConfigurationStatus()],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        try {
            // Get full path to profile photo
            $uploadsDir = $projectDir . '/public/agrilink/uploads';
            $imagePath = $uploadsDir . '/' . $user->getPhotoProfil();

            // Generate avatar from profile photo
            $avatarImageData = $avatarService->generateAvatar($imagePath, $user->getPhotoProfil());

            // Save generated avatar as temporary file
            $tempAvatarPath = $uploadsDir . '/temp_avatar_' . uniqid() . '.png';
            $written = file_put_contents($tempAvatarPath, $avatarImageData);
            
            if ($written === false) {
                throw new \Exception('Failed to write temporary avatar file to disk.');
            }

            try {
                // Create UploadedFile from the generated image
                $avatarFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $tempAvatarPath,
                    'avatar-' . uniqid() . '.png',
                    'image/png',
                    \UPLOAD_ERR_OK,
                    true
                );

                // Upload the generated avatar, replacing the original photo
                $newPhotoPath = $fileUploader->upload(
                    $avatarFile,
                    'profiles',
                    $user->getPhotoProfil()
                );

                $user->setPhotoProfil($newPhotoPath);
                $entityManager->flush();

                $entityManager->refresh($user);
                $token = $security->getToken();
                if ($token) {
                    $token->setUser($user);
                }

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Votre avatar a été généré avec succès!',
                    'photo' => $newPhotoPath,
                ]);
            } finally {
                // Clean up temporary file in finally block to ensure cleanup
                if (file_exists($tempAvatarPath)) {
                    @unlink($tempAvatarPath);
                }
            }
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de la génération de l\'avatar: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @param array<mixed> $values
     * @return list<float>|null
     */
    private function normalizeNumericList(array $values, int $expectedCount): ?array
    {
        if (\count($values) !== $expectedCount) {
            return null;
        }

        $list = [];
        foreach (array_values($values) as $value) {
            if (!is_int($value) && !is_float($value)) {
                return null;
            }
            $list[] = (float) $value;
        }

        return $list;
    }
}
