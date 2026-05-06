<?php

namespace App\Service;

use App\Entity\UserManagement\User;

/**
 * Generates contextual notifications for user profile completion & security.
 * Reminds users to verify email, phone, enable 2FA, and complete profile info.
 */
class UserProfileNotificationService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Generate all contextual notifications for a user based on their profile state.
     * Called after login to show relevant reminders.
     *
     * @return list<array{
     *   type: string,
     *   icon: string,
     *   title: string,
     *   body: string,
     *   priority: string,
     *   actionUrl: string,
     *   actionLabel: string,
     *   dismissible: bool,
     *   badge?: string,
     *   missingFields?: list<string>
     * }>
     */
    public function generateContextualNotifications(User $user): array
    {
        $notifications = [];

        // 1. Email verification reminder
        if (!$user->isEmailVerified()) {
            $notifications[] = $this->createEmailVerificationReminder($user);
        }

        // 2. Phone verification reminder
        if (!$user->isPhoneVerified()) {
            $notifications[] = $this->createPhoneVerificationReminder($user);
        }

        // 3. Two-factor authentication reminder
        if (!$user->isTwoFactorEnabled()) {
            $notifications[] = $this->createTwoFactorReminder($user);
        }

        // 4. Profile completion reminders
        $missingFields = $this->getMissingProfileFields($user);
        if (!empty($missingFields)) {
            $notifications[] = $this->createProfileCompletionReminder($user, $missingFields);
        }

        return $notifications;
    }

    /**
     * Create welcome message after successful login.
     */
    public function createWelcomeNotification(User $user): void
    {
        $prenom = $user->getNom() ?: 'utilisateur';
        
        $welcomeMessages = [
            "Bienvenue, {$prenom}! 🌟",
            "Salut {$prenom}! Content de te voir 👋",
            "Bonjour {$prenom}! Comment ça va? 🤗",
            "Hé {$prenom}! Prêt à cultiver? 🌱",
            "Welcome back, {$prenom}! 💚",
        ];

        $randomWelcome = $welcomeMessages[array_rand($welcomeMessages)];
        
        $bodies = [
            "Excellente journée pour gérer vos cultures! Voici votre tableau de bord personnalisé.",
            "Votre exploitation vous attend. Consultez les dernières mises à jour et statistiques.",
            "Prêt à optimiser votre production? Explorez tous les outils disponibles.",
            "Tout est à jour. Consultez vos exploitations et découvrez les dernières tendances.",
        ];

        $randomBody = $bodies[array_rand($bodies)];

        $this->notificationService->notifierUser(
            $user,
            'user_welcome',
            $randomWelcome,
            $randomBody
        );
    }

    /**
     * Create email verification reminder notification.
     *
     * @return array{
     *   type: string,
     *   icon: string,
     *   title: string,
     *   body: string,
     *   priority: string,
     *   actionUrl: string,
     *   actionLabel: string,
     *   dismissible: bool
     * }
     */
    private function createEmailVerificationReminder(User $user): array
    {
        return [
            'type' => 'email_verification_pending',
            'icon' => '📧',
            'title' => 'Vérifiez votre adresse e-mail',
            'body' => 'Confirmez votre adresse e-mail pour sécuriser votre compte et recevoir des notifications importantes.',
            'priority' => 'high',
            'actionUrl' => '/verify-email',
            'actionLabel' => 'Vérifier maintenant',
            'dismissible' => true,
        ];
    }

    /**
     * Create phone verification reminder notification.
     *
     * @return array{
     *   type: string,
     *   icon: string,
     *   title: string,
     *   body: string,
     *   priority: string,
     *   actionUrl: string,
     *   actionLabel: string,
     *   dismissible: bool
     * }
     */
    private function createPhoneVerificationReminder(User $user): array
    {
        return [
            'type' => 'phone_verification_pending',
            'icon' => '📱',
            'title' => 'Vérifiez votre numéro de téléphone',
            'body' => 'Ajoutez un numéro de téléphone vérifié pour une sécurité accrue et les notifications SMS.',
            'priority' => 'medium',
            'actionUrl' => '/verify-phone',
            'actionLabel' => 'Vérifier maintenant',
            'dismissible' => true,
        ];
    }

    /**
     * Create two-factor authentication reminder notification.
     *
     * @return array{
     *   type: string,
     *   icon: string,
     *   title: string,
     *   body: string,
     *   priority: string,
     *   actionUrl: string,
     *   actionLabel: string,
     *   dismissible: bool,
     *   badge: string
     * }
     */
    private function createTwoFactorReminder(User $user): array
    {
        return [
            'type' => '2fa_setup_pending',
            'icon' => '🔐',
            'title' => 'Activez l\'authentification à deux facteurs',
            'body' => 'Protégez votre compte avec la double authentification. Une couche de sécurité supplémentaire pour votre tranquillité d\'esprit.',
            'priority' => 'high',
            'actionUrl' => '/2fa/setup',
            'actionLabel' => 'Activer 2FA',
            'dismissible' => true,
            'badge' => '⭐ Recommandé',
        ];
    }

    /**
     * Create profile completion reminder with specific missing fields.
     *
     * @param list<string> $missingFields
     * @return array{
     *   type: string,
     *   icon: string,
     *   title: string,
     *   body: string,
     *   priority: string,
     *   actionUrl: string,
     *   actionLabel: string,
     *   dismissible: bool,
     *   missingFields: list<string>
     * }
     */
    private function createProfileCompletionReminder(User $user, array $missingFields): array
    {
        $fieldLabels = [
            'photoProfil' => 'photo de profil',
            'ville' => 'ville',
            'gouvernant' => 'gouvernorat',
            'codePostale' => 'code postal',
            'dateNaissance' => 'date de naissance',
        ];

        $missingLabels = array_map(
            fn($field) => $fieldLabels[$field] ?? $field,
            $missingFields
        );

        $fieldList = implode(', ', $missingLabels);

        return [
            'type' => 'profile_completion_pending',
            'icon' => '👤',
            'title' => 'Complétez votre profil',
            'body' => "Ajoutez les informations manquantes : {$fieldList}. Un profil complet augmente la confiance et améliore votre visibilité.",
            'priority' => 'low',
            'actionUrl' => '/profile/edit',
            'actionLabel' => 'Compléter le profil',
            'dismissible' => true,
            'missingFields' => $missingFields,
        ];
    }

    /**
     * Determine which profile fields are missing or incomplete.
     *
     * @return list<string>
     */
    private function getMissingProfileFields(User $user): array
    {
        $missing = [];

        // Photo profile
        if (!$user->getPhotoProfil()) {
            $missing[] = 'photoProfil';
        }

        // Location info
        if (!$user->getVille()) {
            $missing[] = 'ville';
        }

        if (!$user->getGouvernant()) {
            $missing[] = 'gouvernant';
        }

        if (!$user->getCodePostale()) {
            $missing[] = 'codePostale';
        }

        // Birth date (optional but recommended)
        if (!$user->getDateNaissance()) {
            $missing[] = 'dateNaissance';
        }

        return $missing;
    }

    /**
     * Store contextual notifications in database.
     */
    /**
     * @param list<array{type:string,icon:string,title:string,body:string}> $notifications
     */
    public function persistNotifications(User $user, array $notifications): void
    {
        foreach ($notifications as $notif) {
            $this->notificationService->notifierUser(
                $user,
                $notif['type'],
                $notif['icon'] . ' ' . $notif['title'],
                $notif['body']
            );
        }
    }

    /**
     * Get completion score for user profile (0-100).
     * Used to show progress in UI.
     */
    public function getProfileCompletionScore(User $user): int
    {
        $fields = [
            'nom' => $user->getNom(),
            'email' => $user->getEmail(),
            'telephone' => $user->getTelephone(),
            'photoProfil' => $user->getPhotoProfil(),
            'dateNaissance' => $user->getDateNaissance(),
            'ville' => $user->getVille(),
            'gouvernant' => $user->getGouvernant(),
            'codePostale' => $user->getCodePostale(),
            'emailVerified' => $user->isEmailVerified(),
            'phoneVerified' => $user->isPhoneVerified(),
            '2faEnabled' => $user->isTwoFactorEnabled(),
        ];

        $completed = array_filter($fields, fn($value) => !empty($value));
        $total = count($fields);

        return (int) round((count($completed) / $total) * 100);
    }
}
