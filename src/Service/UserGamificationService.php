<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;

class UserGamificationService
{
    // ── Badge definitions ────────────────────────────────────────────────────
    public const BADGES = [
        'first_login'       => ['key' => 'first_login',    'label' => 'Premier Pas',  'icon' => '🚀', 'description' => 'Première connexion',                    'tip' => 'Connectez-vous pour la première fois — déjà fait si vous lisez ceci !'],
        'loyal_10'          => ['key' => 'loyal_10',        'label' => 'Fidèle',       'icon' => '📅', 'description' => '10 connexions',                         'tip' => 'Revenez régulièrement sur AgriLink — chaque connexion compte.'],
        'regular_50'        => ['key' => 'regular_50',      'label' => 'Habitué',      'icon' => '🔥', 'description' => '50 connexions',                         'tip' => 'Consultez votre tableau de bord chaque jour pour accumuler des connexions.'],
        'fan_100'           => ['key' => 'fan_100',         'label' => 'Fan',          'icon' => '❤️', 'description' => '100 connexions — badge Fan !',           'tip' => 'Atteignez 100 connexions pour décrocher le badge Fan emblématique !'],
        'veteran_500'       => ['key' => 'veteran_500',     'label' => 'Vétéran',      'icon' => '🏆', 'description' => '500 connexions',                        'tip' => 'Les vrais vétérans reviennent chaque jour — 500 connexions vous attendent.'],
        'email_verified'    => ['key' => 'email_verified',  'label' => 'Vérifié',      'icon' => '✉️', 'description' => 'Email vérifié',                         'tip' => 'Vérifiez votre adresse e-mail depuis la bannière en haut du tableau de bord.'],
        'phone_verified'    => ['key' => 'phone_verified',  'label' => 'Mobile',       'icon' => '📱', 'description' => 'Téléphone vérifié',                     'tip' => 'Ajoutez et vérifiez votre numéro de téléphone dans Profil → Sécurité.'],
        'secured'           => ['key' => 'secured',         'label' => 'Sécurisé',     'icon' => '🔒', 'description' => '2FA activé',                            'tip' => 'Activez la double authentification dans Profil → Sécurité → Authentification 2FA.'],
        'face_enrolled'     => ['key' => 'face_enrolled',   'label' => 'Biométrie',    'icon' => '👁️', 'description' => 'Reconnaissance faciale activée',        'tip' => 'Enregistrez votre visage dans Profil → Sécurité → Reconnaissance faciale.'],
        'has_photo'         => ['key' => 'has_photo',       'label' => 'Photographe',  'icon' => '🖼️', 'description' => 'Photo de profil ajoutée',               'tip' => 'Ajoutez une photo de profil depuis Profil → Modifier mon profil.'],
        'profile_complete'  => ['key' => 'profile_complete','label' => 'Complet',      'icon' => '📋', 'description' => 'Profil 100% complété',                  'tip' => 'Renseignez tous vos champs (ville, gouvernorat, date de naissance) dans Modifier mon profil.'],
        'veteran_account'   => ['key' => 'veteran_account', 'label' => 'Ancienneté',   'icon' => '🌟', 'description' => 'Compte actif depuis 6+ mois',           'tip' => 'Continuez à utiliser AgriLink — ce badge s\'obtient avec le temps (6 mois).'],
    ];

    // ── Level definitions (min points required) ──────────────────────────────
    public const LEVELS = [
        1 => ['label' => 'Graine',   'icon' => '🌱', 'min' => 0,    'max' => 49],
        2 => ['label' => 'Apprenti', 'icon' => '🌿', 'min' => 50,   'max' => 149],
        3 => ['label' => 'Actif',    'icon' => '🌾', 'min' => 150,  'max' => 299],
        4 => ['label' => 'Régulier', 'icon' => '🏅', 'min' => 300,  'max' => 499],
        5 => ['label' => 'Expert',   'icon' => '⭐', 'min' => 500,  'max' => 799],
        6 => ['label' => 'Vétéran',  'icon' => '🌟', 'min' => 800,  'max' => 1199],
        7 => ['label' => 'Légende',  'icon' => '🏆', 'min' => 1200, 'max' => PHP_INT_MAX],
    ];

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Recompute points + badges for the given user and persist.
     */
    public function recalculate(User $user): void
    {
        $points = $this->computePoints($user);
        $badges = $this->computeBadges($user);

        $user->setUserPoints($points);
        $user->setEarnedBadges($badges);

        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * Full points breakdown for a user.
     * @phpstan-impure
     */
    public function computePoints(User $user): int
    {
        $pts = 0;

        // Login activity (5 pts each, capped at 500)
        $pts += min($user->getLoginCount() * 5, 500);

        // Security features
        if ($user->isEmailVerified())       $pts += 20;
        if ($user->isPhoneVerified())       $pts += 20;
        if ($user->isTwoFactorEnabled())    $pts += 30;
        if ($user->getFaceDescriptor())     $pts += 40;

        // Profile fields (10 pts each)
        if ($user->getPhotoProfil())        $pts += 15;
        if ($user->getTelephone())          $pts += 10;
        if ($user->getGouvernant())         $pts += 10;
        if ($user->getVille())              $pts += 10;
        if ($user->getDateNaissance())      $pts += 10;
        if ($user->getCodePostale())        $pts += 5;

        // Account seniority (5 pts/month, capped at 60)
        $months = (int) $user->getCreatedAt()->diff(new \DateTime())->m
                + ((int) $user->getCreatedAt()->diff(new \DateTime())->y * 12);
        $pts += min($months * 5, 60);

        return $pts;
    }

    /**
     * Compute all earned badge keys for a user.
     * @phpstan-impure
     */
    public function computeBadges(User $user): array
    {
        $earned = [];
        $logins = $user->getLoginCount();

        if ($logins >= 1)   $earned[] = 'first_login';
        if ($logins >= 10)  $earned[] = 'loyal_10';
        if ($logins >= 50)  $earned[] = 'regular_50';
        if ($logins >= 100) $earned[] = 'fan_100';
        if ($logins >= 500) $earned[] = 'veteran_500';

        if ($user->isEmailVerified())    $earned[] = 'email_verified';
        if ($user->isPhoneVerified())    $earned[] = 'phone_verified';
        if ($user->isTwoFactorEnabled()) $earned[] = 'secured';
        if ($user->getFaceDescriptor())  $earned[] = 'face_enrolled';
        if ($user->getPhotoProfil())     $earned[] = 'has_photo';

        // Profile completeness (all main fields filled)
        $profileScore = $this->profileCompletionScore($user);
        if ($profileScore >= 100) $earned[] = 'profile_complete';

        // Account seniority >= 6 months
        $months = (int) $user->getCreatedAt()->diff(new \DateTime())->m
                + ((int) $user->getCreatedAt()->diff(new \DateTime())->y * 12);
        if ($months >= 6) $earned[] = 'veteran_account';

        return $earned;
    }

    /**
     * Returns the level info array for the given points total.
     */
    public function getLevel(int $points): array
    {
        $level = self::LEVELS[1];
        $levelNumber = 1;

        foreach (self::LEVELS as $num => $def) {
            if ($points >= $def['min']) {
                $level = $def;
                $levelNumber = $num;
            }
        }

        $level['number'] = $levelNumber;

        // Progress toward next level
        $nextMin = self::LEVELS[$levelNumber + 1]['min'] ?? null;
        if ($nextMin !== null) {
            $range = $nextMin - $level['min'];
            if ($range > 0) {
                $done  = $points - $level['min'];
                $level['progress'] = (int) round(($done / $range) * 100);
                $level['points_to_next'] = $nextMin - $points;
            } else {
                $level['progress'] = 100;
                $level['points_to_next'] = 0;
            }
        } else {
            $level['progress'] = 100;
            $level['points_to_next'] = 0;
        }

        return $level;
    }

    /**
     * Full gamification summary as an array (for JSON API / Twig).
     */
    public function getSummary(User $user): array
    {
        $points      = $user->getUserPoints();
        $level       = $this->getLevel($points);
        $earnedKeys  = $user->getEarnedBadges();

        $badgeDetails = array_values(array_filter(
            array_map(fn(string $key) => self::BADGES[$key] ?? null, $earnedKeys)
        ));

        // All badges with earned flag (for achievements panel)
        $allBadgesWithStatus = array_map(function (array $badge) use ($earnedKeys) {
            $badge['earned'] = in_array($badge['key'], $earnedKeys, true);
            return $badge;
        }, array_values(self::BADGES));

        return [
            'points'        => $points,
            'loginCount'    => $user->getLoginCount(),
            'level'         => $level,
            'levels'        => self::LEVELS,
            'badges'        => $badgeDetails,
            'allBadges'     => $allBadgesWithStatus,
            'nextBadges'    => $this->getNextBadges($user),
            'profileScore'  => $this->profileCompletionScore($user),
        ];
    }

    /**
     * Simple 0-100 profile completion score (replicates existing widget logic server-side).
     */
    public function profileCompletionScore(User $user): int
    {
        $fields = [
            $user->getNom(),
            $user->getEmail(),
            $user->getTelephone(),
            $user->getPhotoProfil(),
            $user->getGouvernant(),
            $user->getVille(),
            $user->getDateNaissance(),
        ];

        $filled = count(array_filter($fields, fn($v) => $v !== null && $v !== ''));
        $score  = (int) round(($filled / count($fields)) * 100);

        // Bonus for security
        if ($user->isEmailVerified())    $score = min(100, $score + 0); // already counted in email
        if ($user->isTwoFactorEnabled()) $score = min(100, $score + 5);
        if ($user->isPhoneVerified())    $score = min(100, $score + 5);

        return min(100, $score);
    }

    /**
     * Returns badge definitions the user hasn't earned yet (hints).
     */
    private function getNextBadges(User $user): array
    {
        $earned = $user->getEarnedBadges();
        $next   = [];

        foreach (self::BADGES as $key => $badge) {
            if (!in_array($key, $earned, true)) {
                $next[] = $badge;
                if (count($next) >= 3) break; // show max 3 hints
            }
        }

        return $next;
    }
}
