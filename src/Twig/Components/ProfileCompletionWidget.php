<?php

namespace App\Twig\Components;

use App\Entity\UserManagement\User;
use App\Service\UserProfileNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Displays user profile completion progress bar and status.
 */
class ProfileCompletionWidget extends AbstractController
{
    public function __construct(
        private UserProfileNotificationService $profileNotificationService,
    ) {}

    public function getCompletionData(): array
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return ['score' => 0, 'status' => 'unknown'];
        }

        $score = $this->profileNotificationService->getProfileCompletionScore($user);
        
        return [
            'score' => $score,
            'status' => $this->getStatusLabel($score),
            'statusClass' => $this->getStatusClass($score),
            'color' => $this->getColorForScore($score),
            'motivationalMessage' => $this->getMotivationalMessage($score),
        ];
    }

    private function getStatusLabel(int $score): string
    {
        return match (true) {
            $score === 100 => '✅ Profil complet',
            $score >= 80 => '🎯 Presque là!',
            $score >= 60 => '📝 À moitié complet',
            $score >= 40 => '⚠️ Commencé',
            default => '🚀 À remplir',
        };
    }

    private function getStatusClass(int $score): string
    {
        return match (true) {
            $score === 100 => 'complete',
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            default => 'incomplete',
        };
    }

    private function getColorForScore(int $score): string
    {
        return match (true) {
            $score === 100 => '#22c55e',
            $score >= 80 => '#06b6d4',
            $score >= 60 => '#f59e0b',
            $score >= 40 => '#ef6461',
            default => '#6b7280',
        };
    }

    private function getMotivationalMessage(int $score): string
    {
        return match (true) {
            $score === 100 => 'Excellent travail! Votre profil est complet et attrayant.',
            $score >= 80 => 'Presque terminé! Quelques détails de plus pour briller.',
            $score >= 60 => 'Bon début! Continuez pour augmenter votre visibilité.',
            $score >= 40 => 'Vous êtes sur la bonne voie, continuez!',
            default => 'Commencez à remplir votre profil pour le rendre plus attractif.',
        };
    }
}
