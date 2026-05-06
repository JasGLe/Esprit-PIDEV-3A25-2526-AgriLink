<?php

namespace App\Tests\Service;

use App\Entity\UserManagement\User;
use App\Service\UserModulesManager;
use PHPUnit\Framework\TestCase;

final class UserModulesManagerTest extends TestCase
{
    private UserModulesManager $userModulesManager;

    protected function setUp(): void
    {
        $this->userModulesManager = new UserModulesManager();
    }

    public function testCanEnableTwoFactorWithVerifiedEmailPasses(): void
    {
        $user = new User();
        $user->setEmailVerified(true);
        $user->setPhoneVerified(false);

        $this->assertTrue($this->userModulesManager->canEnableTwoFactor($user));
    }

    public function testCanEnableTwoFactorWithVerifiedPhonePasses(): void
    {
        $user = new User();
        $user->setEmailVerified(false);
        $user->setPhoneVerified(true);

        $this->assertTrue($this->userModulesManager->canEnableTwoFactor($user));
    }

    public function testCanEnableTwoFactorWithoutVerifiedEmailOrPhoneThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Veuillez vérifier votre email ou votre téléphone avant d\'activer le 2FA');

        $user = new User();
        $user->setEmailVerified(false);
        $user->setPhoneVerified(false);

        $this->userModulesManager->canEnableTwoFactor($user);
    }

    public function testIsFaceEnrolledWithDescriptorPasses(): void
    {
        $user = new User();
        $user->setFaceDescriptor(json_encode(array_fill(0, 128, 0.1), JSON_THROW_ON_ERROR));

        $this->assertTrue($this->userModulesManager->isFaceEnrolled($user));
    }

    public function testIsFaceEnrolledWithoutDescriptorThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Aucun visage enregistré');

        $user = new User();
        $user->setFaceDescriptor(null);

        $this->userModulesManager->isFaceEnrolled($user);
    }

    public function testValidateFaceDescriptorArrayWith128NumericValuesPasses(): void
    {
        $descriptor = array_fill(0, 128, 0.123);

        $this->assertTrue($this->userModulesManager->validateFaceDescriptorArray($descriptor));
    }

    public function testValidateFaceDescriptorArrayWithWrongCountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Format de descriptor invalide (128 valeurs attendues)');

        $descriptor = array_fill(0, 127, 0.123);

        $this->userModulesManager->validateFaceDescriptorArray($descriptor);
    }

    public function testValidateFaceDescriptorArrayWithNonNumericValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le descriptor doit contenir uniquement des nombres');

        $descriptor = array_fill(0, 128, 0.123);
        $descriptor[0] = 'not-a-number';

        $this->userModulesManager->validateFaceDescriptorArray($descriptor);
    }

    public function testValidateVoiceEnrollmentWithValidEmbeddingAndDurationPasses(): void
    {
        $embedding = [0.1, 0.2, 0.3];

        $this->assertTrue($this->userModulesManager->validateVoiceEnrollment($embedding, 4));
    }

    public function testValidateVoiceEnrollmentWithEmptyEmbeddingThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voice embedding array is empty');

        $this->userModulesManager->validateVoiceEnrollment([], 4);
    }

    public function testValidateVoiceEnrollmentWithInvalidDurationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Audio duration must be 3-5 seconds');

        $this->userModulesManager->validateVoiceEnrollment([0.1], 2);
    }

    public function testValidateVoiceEnrollmentWithNonNumericEmbeddingThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voice embedding must contain only numbers');

        $this->userModulesManager->validateVoiceEnrollment(['x'], 4);
    }
}

