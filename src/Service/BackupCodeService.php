<?php

namespace App\Service;

use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;

class BackupCodeService
{
    private const CODE_COUNT = 8;
    private const CODE_LENGTH = 8;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generate(User $user): array
    {
        $plainCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $code = $this->generateRandomCode();
            $plainCodes[] = $code;
            $hashedCodes[] = [
                'code' => hash('sha256', $code),
                'used' => false,
            ];
        }

        $user->setBackupCodes($hashedCodes);
        $this->entityManager->flush();

        return $plainCodes;
    }

    public function verify(User $user, string $inputCode): bool
    {
        $codes = $user->getBackupCodes();
        if (empty($codes)) {
            return false;
        }

        $inputHash = hash('sha256', strtoupper(trim($inputCode)));

        foreach ($codes as $index => $entry) {
            if (!$entry['used'] && hash_equals($entry['code'], $inputHash)) {
                $codes[$index]['used'] = true;
                $user->setBackupCodes($codes);
                $this->entityManager->flush();
                return true;
            }
        }

        return false;
    }

    public function getRemainingCount(User $user): int
    {
        $codes = $user->getBackupCodes();
        if (empty($codes)) {
            return 0;
        }

        return count(array_filter($codes, fn($entry) => !$entry['used']));
    }

    private function generateRandomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
