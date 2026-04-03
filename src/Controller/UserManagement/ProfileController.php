<?php

namespace App\Controller\UserManagement;

use App\Entity\UserManagement\User;
use App\Form\UserManagement\ChangePasswordType;
use App\Form\UserManagement\ProfileEditFormType;
use App\Service\FileUploader;
use App\Service\SecurityEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
        Security $security
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        
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
                        
                        // Refresh user and update security token
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
            // Gestion de l'upload de photo (if included in main form)
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
            
            // Refresh the user entity to ensure we have the latest data
            $entityManager->refresh($user);
            
            // Update the security token with the refreshed user
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
        SecurityEventService $securityEventService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Vérifier le mot de passe actuel
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            // Hasher et définir le nouveau mot de passe
            $newPassword = $form->get('newPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            // Logger l'événement de sécurité
            $securityEventService->logPasswordChanged($user);

            // Envoyer l'email de notification
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@agrilink.com', 'AgriLink'))
                    ->to($user->getEmail())
                    ->subject('Votre mot de passe AgriLink a été modifié')
                    ->htmlTemplate('emails/password_changed.html.twig')
                    ->context([
                        'user' => $user,
                        'date' => new \DateTime(),
                    ]);

                $mailer->send($email);
            } catch (\Exception $e) {
                // Continuer même si l'email échoue - le mot de passe est déjà changé
            }

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user_management/profile/change_password.html.twig', [
            'form' => $form,
        ]);
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
                // Delete the physical file
                $fileUploader->deleteFile($user->getPhotoProfil());
                
                // Clear the database field
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
