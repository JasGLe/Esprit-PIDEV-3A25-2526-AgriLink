<?php

namespace App\Security;

use App\Entity\UserManagement\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if account is banned (permanent)
        if ($user->isBanned()) {
            $reason = $user->getBanReason() ?: 'Violation des conditions d\'utilisation';
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été banni : ' . $reason . '. Contactez le support pour plus d\'informations.'
            );
        }

        // Check if account is locked
        if ($user->isLockedOut()) {
            $lockedUntil = $user->getLockedUntil();
            $remainingMinutes = ceil(($lockedUntil->getTimestamp() - time()) / 60);
            
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est temporairement verrouillé suite à plusieurs tentatives de connexion échouées. Veuillez réessayer dans ' . $remainingMinutes . ' minute(s).'
            );
        }

        // Check if account is active
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est désactivé. Veuillez contacter un administrateur.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check email verification (warning only, allow login for now)
        // This could be used to display a flash message or redirect to verification page
        // For now, we allow login but this check is available for future use
    }
}
