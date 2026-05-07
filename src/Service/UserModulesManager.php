<?php

namespace App\Service;

use App\Entity\UserManagement\User;

/**
 * UserModulesManager Service
 *
 * Centralizes business rules for user security modules:
 * - 2FA eligibility
 * - Face descriptor validation
 * - Voice embedding validation
 */
class UserModulesManager
{
    public function canEnableTwoFactor(User $user): bool
    {
        if (!$user->isEmailVerified() && !$user->isPhoneVerified()) {
            throw new \InvalidArgumentException('Veuillez vérifier votre email ou votre téléphone avant d\'activer le 2FA');
        }

        return true;
    }

    public function isFaceEnrolled(User $user): bool
    {
        if (empty($user->getFaceDescriptor())) {
            throw new \InvalidArgumentException('Aucun visage enregistré');
        }

        return true;
    }

    /**
     * @param array<mixed> $descriptor
     */
    public function validateFaceDescriptorArray(array $descriptor): bool
    {
        if (\count($descriptor) !== 128) {
            throw new \InvalidArgumentException('Format de descriptor invalide (128 valeurs attendues)');
        }

        foreach ($descriptor as $value) {
            if (!\is_int($value) && !\is_float($value)) {
                throw new \InvalidArgumentException('Le descriptor doit contenir uniquement des nombres');
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $embedding
     */
    public function validateVoiceEnrollment(array $embedding, int $audioDurationSeconds): bool
    {
        if (empty($embedding)) {
            throw new \InvalidArgumentException('Voice embedding array is empty');
        }

        if ($audioDurationSeconds < 3 || $audioDurationSeconds > 5) {
            throw new \InvalidArgumentException('Audio duration must be 3-5 seconds');
        }

        foreach ($embedding as $value) {
            if (!\is_int($value) && !\is_float($value)) {
                throw new \InvalidArgumentException('Voice embedding must contain only numbers');
            }
        }

        return true;
    }
}

