<?php

namespace App\Tests\Service;

use App\Entity\UserManagement\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * UserManagerTest
 * 
 * Tests the business rules for User entity validation:
 * 1. Nom (last name) is mandatory
 * 2. Email must be valid
 * 3. Password must have at least 8 characters
 * 4. Email must be unique
 * 5. Role must be valid
 * 6. User must be active
 */
class UserManagerTest extends TestCase
{
    private UserManager $userManager;

    protected function setUp(): void
    {
        $this->userManager = new UserManager();
    }

    // ============================================================
    // TEST 1: Validate valid user data
    // ============================================================

    /**
     * Test: Valid user with all required fields passes validation
     */
    public function testValidUserWithAllRequiredFields(): void
    {
        $user = new User();
        $user->setNom('Dupont');
        $user->setEmail('jean.dupont@example.com');

        $this->assertTrue($this->userManager->validate($user));
    }

    // ============================================================
    // TEST 2: Nom validation rules
    // ============================================================

    /**
     * Test: User without nom (last name) throws exception
     */
    public function testUserWithoutNomThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new User();
        $user->setEmail('test@example.com');

        $this->userManager->validate($user);
    }

    /**
     * Test: User with empty nom string throws exception
     */
    public function testUserWithEmptyNomThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new User();
        $user->setNom('');
        $user->setEmail('test@example.com');

        $this->userManager->validate($user);
    }

    // ============================================================
    // TEST 3: Email validation rules
    // ============================================================

    /**
     * Test: Invalid email format throws exception
     */
    public function testInvalidEmailFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $user = new User();
        $user->setNom('Martin');
        $user->setEmail('email_invalid');

        $this->userManager->validate($user);
    }

    /**
     * Test: Email without @ symbol throws exception
     */
    public function testEmailWithoutAtSymbolThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = new User();
        $user->setNom('Martin');
        $user->setEmail('emailexample.com');

        $this->userManager->validate($user);
    }

    /**
     * Test: Email with valid format passes validation
     */
    public function testValidEmailFormatsPass(): void
    {
        $validEmails = [
            'user@example.com',
            'john.doe@domain.co.uk',
            'test+tag@example.org',
        ];

        foreach ($validEmails as $email) {
            $user = new User();
            $user->setNom('Test User');
            $user->setEmail($email);

            $this->assertTrue($this->userManager->validate($user));
        }
    }

    // ============================================================
    // TEST 4: Password strength validation
    // ============================================================

    /**
     * Test: Password with at least 8 characters passes validation
     */
    public function testPasswordWithEightCharactersPassesValidation(): void
    {
        $this->assertTrue($this->userManager->validatePassword('SecurePass123!'));
    }

    /**
     * Test: Password with less than 8 characters throws exception
     */
    public function testPasswordWithLessThanEightCharactersThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères');

        $this->userManager->validatePassword('Short1!');
    }

    /**
     * Test: Short password throws exception
     */
    public function testShortPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->userManager->validatePassword('abc');
    }

    /**
     * Test: Empty password throws exception
     */
    public function testEmptyPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->userManager->validatePassword('');
    }

    // ============================================================
    // TEST 5: Email uniqueness validation
    // ============================================================

    /**
     * Test: Unique email passes validation
     */
    public function testUniqueEmailPassesValidation(): void
    {
        $user = new User();
        $user->setEmail('newuser@example.com');

        $existingEmails = ['john@example.com', 'jane@example.com'];

        $this->assertTrue($this->userManager->isEmailUnique($user, $existingEmails));
    }

    /**
     * Test: Duplicate email throws exception
     */
    public function testDuplicateEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cet email est déjà utilisé');

        $user = new User();
        $user->setEmail('existing@example.com');

        $existingEmails = ['existing@example.com', 'other@example.com'];

        $this->userManager->isEmailUnique($user, $existingEmails);
    }

    /**
     * Test: Empty existing emails array allows any email
     */
    public function testEmptyExistingEmailsAllowsAnyEmail(): void
    {
        $user = new User();
        $user->setEmail('anyuser@example.com');

        $this->assertTrue($this->userManager->isEmailUnique($user, []));
    }

    // ============================================================
    // TEST 6: Role validation
    // ============================================================

    /**
     * Test: Valid role AGRICULTEUR passes validation
     */
    public function testValidRoleAgriculteurPassesValidation(): void
    {
        $user = new User();
        $user->setRole(User::ROLE_AGRICULTEUR);

        $this->assertTrue($this->userManager->hasValidRole($user));
    }

    /**
     * Test: Valid role FOURNISSEUR passes validation
     */
    public function testValidRoleFournisseurPassesValidation(): void
    {
        $user = new User();
        $user->setRole(User::ROLE_FOURNISSEUR);

        $this->assertTrue($this->userManager->hasValidRole($user));
    }

    /**
     * Test: Valid role AGRIPLUS passes validation
     */
    public function testValidRoleAgriplusPassesValidation(): void
    {
        $user = new User();
        $user->setRole(User::ROLE_AGRIPLUS);

        $this->assertTrue($this->userManager->hasValidRole($user));
    }

    /**
     * Test: Invalid role throws exception
     */
    public function testInvalidRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rôle utilisateur est invalide');

        $user = new User();
        $user->setRole('INVALID_ROLE');

        $this->userManager->hasValidRole($user);
    }

    /**
     * Test: Empty role throws exception
     */
    public function testEmptyRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = new User();
        $user->setRole('');

        $this->userManager->hasValidRole($user);
    }

    // ============================================================
    // TEST 7: Active status validation
    // ============================================================

    /**
     * Test: Active user passes validation
     */
    public function testActiveUserPassesValidation(): void
    {
        $user = new User();
        $user->setIsActive(true);

        $this->assertTrue($this->userManager->isActive($user));
    }

    /**
     * Test: Inactive user throws exception
     */
    public function testInactiveUserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur n\'est pas actif');

        $user = new User();
        $user->setIsActive(false);

        $this->userManager->isActive($user);
    }

    // ============================================================
    // TEST 8: Integration tests - Multiple rules
    // ============================================================

    /**
     * Test: Multiple valid agriculteur users
     */
    public function testMultipleValidAgriculteurUsers(): void
    {
        $users = [
            $this->createValidUser('Dupont', 'dupont@example.com', User::ROLE_AGRICULTEUR),
            $this->createValidUser('Martin', 'martin@example.com', User::ROLE_AGRICULTEUR),
            $this->createValidUser('Bernard', 'bernard@example.com', User::ROLE_AGRICULTEUR),
        ];

        foreach ($users as $user) {
            $this->assertTrue($this->userManager->validate($user));
            $this->assertTrue($this->userManager->hasValidRole($user));
            $this->assertTrue($this->userManager->isActive($user));
        }
    }

    /**
     * Test: Complete user validation flow
     */
    public function testCompleteUserValidationFlow(): void
    {
        $user = new User();
        $user->setNom('Lefevre');
        $user->setEmail('lefevre@example.com');
        $user->setRole(User::ROLE_FOURNISSEUR);
        $user->setIsActive(true);

        // All validations should pass
        $this->assertTrue($this->userManager->validate($user));
        $this->assertTrue($this->userManager->validatePassword('SecurePass123!'));
        $this->assertTrue($this->userManager->isEmailUnique($user, []));
        $this->assertTrue($this->userManager->hasValidRole($user));
        $this->assertTrue($this->userManager->isActive($user));
    }

    // ============================================================
    // Helper methods
    // ============================================================

    /**
     * Create a valid user for testing
     */
    private function createValidUser(string $nom, string $email, string $role): User
    {
        $user = new User();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setIsActive(true);

        return $user;
    }
}
