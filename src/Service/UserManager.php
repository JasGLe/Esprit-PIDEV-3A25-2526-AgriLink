<?php

namespace App\Service;

use App\Entity\UserManagement\User;

/**
 * UserManager Service
 * 
 * Validates core business rules for User entity:
 * - Email must be valid
 * - Password must have at least 8 characters
 * - Nom (last name) is mandatory
 */
class UserManager
{
    /**
     * Validate user data according to business rules
     * 
     * @param User $user The user to validate
     * @return bool True if user is valid
     * @throws \InvalidArgumentException If any business rule is violated
     */
    public function validate(User $user): bool
    {
        // Rule 1: Nom (last name) is mandatory
        if (empty($user->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        // Rule 2: Email must be valid
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        return true;
    }

    /**
     * Validate password strength
     * 
     * @param string $password The plain password to validate
     * @return bool True if password meets requirements
     * @throws \InvalidArgumentException If password doesn't meet requirements
     */
    public function validatePassword(string $password): bool
    {
        // Rule: Password must have at least 8 characters
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
        }

        return true;
    }

    /**
     * Check if user email is unique
     * Note: In real application, this would query the database
     * 
     * @param User $user The user to check
     * @param list<string> $existingEmails Array of existing emails for testing
     * @return bool True if email is unique
     * @throws \InvalidArgumentException If email already exists
     */
    public function isEmailUnique(User $user, array $existingEmails = []): bool
    {
        if (in_array($user->getEmail(), $existingEmails, true)) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé');
        }

        return true;
    }

    /**
     * Verify user has required role
     * 
     * @param User $user The user to check
     * @return bool True if user has valid role
     * @throws \InvalidArgumentException If user has no role
     */
    public function hasValidRole(User $user): bool
    {
        $validRoles = [User::ROLE_AGRICULTEUR, User::ROLE_FOURNISSEUR, User::ROLE_AGRIPLUS, User::ROLE_USER];
        
        if (empty($user->getRole()) || !in_array($user->getRole(), $validRoles)) {
            throw new \InvalidArgumentException('Le rôle utilisateur est invalide');
        }

        return true;
    }

    /**
     * Verify user is active
     * 
     * @param User $user The user to check
     * @return bool True if user is active
     * @throws \InvalidArgumentException If user is not active
     */
    public function isActive(User $user): bool
    {
        if (!$user->isActive()) {
            throw new \InvalidArgumentException('L\'utilisateur n\'est pas actif');
        }

        return true;
    }
}
