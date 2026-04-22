<?php

namespace App\Tests\Service;

use App\Entity\UserManagement\User;
use App\Service\BackupCodeService;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class CodeVerificationTest extends TestCase
{
    private BackupCodeService $backupCodeService;
    private EmailVerificationService $emailVerificationService;
    private User $testUser;

    protected function setUp(): void
    {
        // Mock dependencies
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $twig = $this->createMock(Environment::class);

        // Create services
        $this->backupCodeService = new BackupCodeService($entityManager);
        $this->emailVerificationService = new EmailVerificationService(
            $entityManager,
            $mailer,
            $twig,
            'test@agrilink.com',
            'http://localhost'
        );

        // Create test user
        $this->testUser = new User();
        $this->testUser->setEmail('test@example.com');
        $this->testUser->setPassword('hashed_password');
        $this->testUser->setRole(User::ROLE_AGRICULTEUR);
        $this->testUser->setIsActive(true);
    }

    /**
     * Test backup codes contain alphanumeric characters
     */
    public function testBackupCodesContainLetters(): void
    {
        // Generate backup codes
        $codes = $this->backupCodeService->generate($this->testUser);
        
        // Verify we have 8 codes
        $this->assertCount(8, $codes);
        
        // Verify each code has letters (not just numbers)
        foreach ($codes as $code) {
            // Should be 8 characters
            $this->assertEquals(8, strlen($code));
            
            // Should contain letters (A-Z) and numbers (2-9)
            $this->assertTrue(
                preg_match('/[A-Z]/', $code) === 1,
                "Code '{$code}' should contain at least one letter"
            );
        }
    }

    /**
     * Test email verification codes are numeric only
     */
    public function testEmailVerificationCodesAreNumeric(): void
    {
        // Generate verification code
        $code = $this->emailVerificationService->generateVerificationCode($this->testUser);
        
        // Should be 6 characters
        $this->assertEquals(6, strlen($code));
        
        // Should contain only digits
        $this->assertTrue(
            ctype_digit($code),
            "Email verification code '{$code}' should contain only digits"
        );
    }

    /**
     * Test that backup codes are NOT stripped when processed
     */
    public function testBackupCodePreservesLetters(): void
    {
        $sampleBackupCode = 'ABC2D9EF';
        
        // Simulate what SHOULD happen (preserve the code)
        $preserved = trim($sampleBackupCode);
        $this->assertEquals('ABC2D9EF', $preserved);
        
        // Simulate what SHOULD NOT happen (old buggy code that removes letters)
        $stripped = preg_replace('/[^0-9]/', '', $sampleBackupCode);
        $this->assertEquals('29', $stripped);
        
        // Verify they're different
        $this->assertNotEquals($stripped, $preserved);
    }

    /**
     * Test that OTP codes can safely be stripped for cleaning
     */
    public function testOtpCodeCanBeStripedForCleaning(): void
    {
        $sampleOtpCode = '123 456'; // User might add spaces
        
        // OTP should be numeric only, so stripping is safe
        $cleaned = preg_replace('/[^0-9]/', '', $sampleOtpCode);
        $this->assertEquals('123456', $cleaned);
        
        // Verify it's 6 digits
        $this->assertTrue(ctype_digit($cleaned));
        $this->assertEquals(6, strlen($cleaned));
    }

    /**
     * Integration test: Show the difference in handling
     */
    public function testDifferentCodeTypesMustBeDifferentlyProcessed(): void
    {
        // Email code: 6 numeric digits
        $emailCode = $this->emailVerificationService->generateVerificationCode($this->testUser);
        $this->assertEquals(6, strlen($emailCode));
        $this->assertTrue(ctype_digit($emailCode));
        
        // Process with OTP cleaning (safe for email codes)
        $emailCodeCleaned = preg_replace('/[^0-9]/', '', $emailCode);
        $this->assertEquals($emailCode, $emailCodeCleaned);
        
        // Backup code: 8 alphanumeric
        $backupCodes = $this->backupCodeService->generate($this->testUser);
        $backupCode = $backupCodes[0];
        $this->assertEquals(8, strlen($backupCode));
        
        // Process with OTP cleaning (DANGEROUS for backup codes)
        $backupCodeCleaned = preg_replace('/[^0-9]/', '', $backupCode);
        $this->assertNotEquals($backupCode, $backupCodeCleaned);
        
        // This shows why we need to handle them separately
        $this->assertTrue(strlen($backupCodeCleaned) < strlen($backupCode));
    }
}
