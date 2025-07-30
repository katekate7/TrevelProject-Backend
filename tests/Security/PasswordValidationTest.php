<?php
/**
 * Test suite for password validation functionality.
 * 
 * This test class validates password strength requirements including:
 * - Minimum length validation (8 characters)
 * - Character diversity requirements (uppercase, lowercase, number, special)
 * - Common weak password detection
 * - Detailed error message validation
 * 
 * @package App\Tests\Security
 * @author Travel Project Team
 */

namespace App\Tests\Security;

use App\Security\SecurityService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for password validation security requirements.
 * 
 * Ensures that password validation meets security standards and provides
 * helpful error messages to users during registration and password reset.
 */
class PasswordValidationTest extends TestCase
{
    private SecurityService $securityService;

    protected function setUp(): void
    {
        $this->securityService = new SecurityService();
    }

    /**
     * Test that strong passwords are accepted.
     */
    public function testValidPasswordsAreAccepted(): void
    {
        $validPasswords = [
            'Test123!',           // Basic valid password
            'MySecure@Password1', // Longer secure password
            'Complex#Pass99',     // Another valid combination
            'Abc123$def456',      // Numbers and special chars
            'P@ssw0rd!Strong',    // Mixed case with special chars
        ];

        foreach ($validPasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertTrue(
                $result['valid'], 
                "Password '{$password}' should be valid but was rejected: " . $result['message']
            );
        }
    }

    /**
     * Test that passwords shorter than 8 characters are rejected.
     */
    public function testShortPasswordsAreRejected(): void
    {
        $shortPasswords = [
            'Test1!',     // 6 characters
            'Ab1!',       // 4 characters
            'Pass@7',     // 6 characters
            '',           // Empty password
        ];

        foreach ($shortPasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Password '{$password}' should be rejected for being too short"
            );
            $this->assertStringContainsString(
                'at least 8 characters', 
                $result['message'],
                "Error message should mention minimum length requirement"
            );
        }
    }

    /**
     * Test that passwords without uppercase letters are rejected.
     */
    public function testPasswordsWithoutUppercaseAreRejected(): void
    {
        $noUppercasePasswords = [
            'test123!',
            'password@456',
            'secure#pass1',
        ];

        foreach ($noUppercasePasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Password '{$password}' should be rejected for missing uppercase letter"
            );
            $this->assertStringContainsString(
                'uppercase letter', 
                $result['message'],
                "Error message should mention uppercase requirement"
            );
        }
    }

    /**
     * Test that passwords without lowercase letters are rejected.
     */
    public function testPasswordsWithoutLowercaseAreRejected(): void
    {
        $noLowercasePasswords = [
            'TEST123!',
            'PASSWORD@456',
            'SECURE#PASS1',
        ];

        foreach ($noLowercasePasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Password '{$password}' should be rejected for missing lowercase letter"
            );
            $this->assertStringContainsString(
                'lowercase letter', 
                $result['message'],
                "Error message should mention lowercase requirement"
            );
        }
    }

    /**
     * Test that passwords without numbers are rejected.
     */
    public function testPasswordsWithoutNumbersAreRejected(): void
    {
        $noNumberPasswords = [
            'TestPass!',
            'Password@Strong',
            'Secure#Pass',
        ];

        foreach ($noNumberPasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Password '{$password}' should be rejected for missing number"
            );
            $this->assertStringContainsString(
                'number', 
                $result['message'],
                "Error message should mention number requirement"
            );
        }
    }

    /**
     * Test that passwords without special characters are rejected.
     */
    public function testPasswordsWithoutSpecialCharsAreRejected(): void
    {
        $noSpecialCharPasswords = [
            'TestPass123',
            'Password456',
            'SecurePass789',
        ];

        foreach ($noSpecialCharPasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Password '{$password}' should be rejected for missing special character"
            );
            $this->assertStringContainsString(
                'special character', 
                $result['message'],
                "Error message should mention special character requirement"
            );
        }
    }

    /**
     * Test that common weak passwords are rejected.
     */
    public function testCommonPasswordsAreRejected(): void
    {
        $commonPasswords = [
            'password',
            'password123',
            '123456',
            'qwerty',
            'admin',
        ];

        foreach ($commonPasswords as $password) {
            $result = $this->securityService->validatePasswordWithMessage($password);
            $this->assertFalse(
                $result['valid'], 
                "Common password '{$password}' should be rejected"
            );
        }
    }

    /**
     * Test that error messages are comprehensive and helpful.
     */
    public function testErrorMessagesAreHelpful(): void
    {
        $result = $this->securityService->validatePasswordWithMessage('weak');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum 8 characters', $result['message']);
        $this->assertStringContainsString('1 uppercase letter', $result['message']);
        $this->assertStringContainsString('1 lowercase letter', $result['message']);
        $this->assertStringContainsString('1 number', $result['message']);
        $this->assertStringContainsString('1 special character', $result['message']);
    }

    /**
     * Test basic password validation method (backward compatibility).
     */
    public function testBasicPasswordValidation(): void
    {
        $this->assertTrue($this->securityService->validatePassword('Test123!'));
        $this->assertFalse($this->securityService->validatePassword('weak'));
        
        // Test password without numbers - should fail
        $this->assertFalse($this->securityService->validatePassword('NoNumbers!'));
        
        // Test password without uppercase - should fail
        $this->assertFalse($this->securityService->validatePassword('nouppercase123!'));
        
        // Test password without lowercase - should fail
        $this->assertFalse($this->securityService->validatePassword('NOLOWERCASE123!'));
        
        // Test password without special characters - should fail
        $this->assertFalse($this->securityService->validatePassword('NoSpecialChar123'));
    }
}
