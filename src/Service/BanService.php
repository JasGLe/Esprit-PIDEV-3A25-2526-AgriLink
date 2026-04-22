<?php

namespace App\Service;

use App\Entity\UserManagement\User;

final class BanService
{
    public function banUser(User $user, string $reason): void
    {
        $now = new \DateTimeImmutable();
        $nextCount = $user->getBanCount() + 1;

        $user->setBanCount($nextCount);
        $user->ban($reason);
        $user->setBannedUntil(null);
        $user->setIsPermanentlyBanned(false);

        if ($nextCount === 1) {
            $user->setBannedUntil($now->modify('+24 hours'));
            return;
        }

        if ($nextCount === 2) {
            $user->setBannedUntil($now->modify('+48 hours'));
            return;
        }

        $user->setIsPermanentlyBanned(true);
    }

    public function liftBan(User $user): void
    {
        $user->unban();
    }

    public function isBlocked(User $user): bool
    {
        if ($user->isPermanentlyBanned()) {
            return true;
        }

        $until = $user->getBannedUntil();
        if ($until !== null) {
            return $until > new \DateTimeImmutable();
        }

        // Legacy/manual ban flag without timed ban metadata.
        return $user->isBanned();
    }
}

