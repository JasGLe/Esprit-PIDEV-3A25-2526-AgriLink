<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\ChangePasswordType;
use App\Form\UserManagement\ProfileEditFormType;
use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserSessionRepository;
use App\Service\BackupCodeService;
use App\Service\EmailVerificationService;
use App\Service\FileUploader;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        /** @var User $user */
        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        // Handle photo-only upload
        if ($request->isMethod('POST') && $request->request->get('photo_only')) {
            if ($this->isCsrfTokenValid('profile_photo_upload', $request->request->get('_token'))) {
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
            if ($newEmail !== $originalEmail) {
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
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            $newPassword = $form->get('newPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $securityEventService->logPasswordChanged($user);

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@agrilink.com', 'AgriLink'))
                    ->to($user->getEmail())
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
        /** @var User $user */
        $user = $this->getUser();

        $recentEvents = $securityEventRepository->findByUser($user->getId(), 8);
        $backupCodesRemaining = $backupCodeService->getRemainingCount($user);

        return $this->render('user_management/profile/security.html.twig', [
            'user'                 => $user,
            'recentEvents'         => $recentEvents,
            'backupCodesRemaining' => $backupCodesRemaining,
        ]);
    }

    #[Route('/security/toggle-2fa', name: 'app_profile_toggle_2fa', methods: ['POST'])]
    public function toggle2fa(
        Request $request,
        EntityManagerInterface $entityManager,
        BackupCodeService $backupCodeService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('toggle_2fa', $request->request->get('_token'))) {
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

            $this->addFlash(
                'success',
                'La double authentification (2FA) a été activée. Sauvegardez vos codes de secours !'
            );

            return $this->redirectToRoute('app_profile_backup_codes');
        }

        $entityManager->flush();

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
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('revoke_all_sessions', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_security');
        }

        $count = $userSessionRepository->revokeAllForUser($user->getId());
        $securityEventService->logSessionRevoked($user);

        $this->addFlash('success', sprintf('%d session(s) ont été révoquées. Vous devrez vous reconnecter sur vos autres appareils.', $count));

        return $this->redirectToRoute('app_profile_security');
    }

    // Feature 5: Login history
    #[Route('/login-history', name: 'app_profile_login_history', methods: ['GET'])]
    public function loginHistory(
        SecurityEventRepository $securityEventRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $events = $securityEventRepository->findLoginHistoryByUser($user->getId());

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
        /** @var User $user */
        $user = $this->getUser();

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
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('regenerate_backup_codes', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_security');
        }

        $plainCodes = $backupCodeService->generate($user);
        $request->getSession()->set('_backup_codes_display', $plainCodes);

        $this->addFlash('success', 'De nouveaux codes de secours ont été générés. Les anciens codes sont invalides.');

        return $this->redirectToRoute('app_profile_backup_codes');
    }

    #[Route('/delete-photo', name: 'app_profile_delete_photo', methods: ['GET'])]
    public function deletePhoto(
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

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
}
