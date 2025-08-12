<?php
/**
 * Security Service
 * 
 * This service provides a collection of security-related utility functions
 * for input validation, sanitization, and protection against common web
 * vulnerabilities such as XSS, CSRF, and SQL injection attacks.
 * 
 * @package App\Security
 * @author Travel Project Team
 */

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Core security service providing multiple layers of protection
 * 
 * This class centralizes security-related functionality including input validation,
 * sanitization, rate limiting, CSRF protection, and password policy enforcement.
 * It serves as a security toolkit for the entire application.
 */
class SecurityService
{
    /**
     * List of allowed HTML tags for controlled HTML content
     * 
     * These tags are considered safe for user input when HTML is allowed.
     * The list is intentionally restrictive to minimize XSS risks.
     * 
     * @var array<string>
     */
    private array $allowedHtmlTags = ['b', 'i', 'strong', 'em', 'p', 'br'];
    
    /**
     * Comprehensive input sanitization
     * 
     * Cleans user input to prevent XSS (Cross-Site Scripting) attacks by removing
     * dangerous HTML/JavaScript content while preserving legitimate text.
     * This is a defense-in-depth measure that should be used for all user inputs.
     * 
     * @param string $input The raw user input to sanitize
     * @param bool $allowHtml Whether to allow limited safe HTML tags (default: false)
     * @return string The sanitized input string
     */
    public function sanitizeInput(string $input, bool $allowHtml = false): string
    {
        // Trim whitespace from beginning and end
        $input = trim($input);
        
        // Handle HTML based on the allowHtml parameter
        if ($allowHtml) {
            // Allow only specific safe HTML tags from the allowedHtmlTags list
            $allowedTags = '<' . implode('><', $this->allowedHtmlTags) . '>';
            $input = strip_tags($input, $allowedTags);
        } else {
            // Remove all HTML tags completely for maximum safety
            $input = strip_tags($input);
        }
        
        // Convert special characters to HTML entities to prevent XSS
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove potential script injections that could bypass other filters
        $input = preg_replace('/javascript:/i', '', $input); // Remove javascript: protocol
        $input = preg_replace('/on\w+\s*=/i', '', $input);   // Remove event handlers (onclick, onload, etc.)
        
        return $input;
    }
    
    /**
     * Validate and sanitize array of inputs
     * 
     * Recursively applies the sanitizeInput method to all string values in an array.
     * This is useful for sanitizing form submissions, JSON data, or any nested array structure.
     * 
     * @param array $inputs The array of input values to sanitize
     * @param bool $allowHtml Whether to allow limited safe HTML tags (default: false)
     * @return array The sanitized array with the same structure
     */
    public function sanitizeArray(array $inputs, bool $allowHtml = false): array
    {
        $sanitized = [];
        foreach ($inputs as $key => $value) {
            if (is_string($value)) {
                // Sanitize string values
                $sanitized[$key] = $this->sanitizeInput($value, $allowHtml);
            } elseif (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$key] = $this->sanitizeArray($value, $allowHtml);
            } else {
                // Pass through non-string, non-array values unchanged
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Validate email format
     * 
     * Checks if a string is a properly formatted email address.
     * This uses PHP's built-in email validation filter for RFC compliance.
     * 
     * @param string $email The email address to validate
     * @return bool True if the email format is valid, false otherwise
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate string length
     * 
     * Ensures a string's length falls within acceptable bounds.
     * Uses multibyte string functions to correctly handle UTF-8 characters.
     * 
     * @param string $input The string to check
     * @param int $min Minimum acceptable length (default: 1)
     * @param int $max Maximum acceptable length (default: 1000)
     * @return bool True if the string length is within range, false otherwise
     */
    public function validateLength(string $input, int $min = 1, int $max = 1000): bool
    {
        $length = mb_strlen($input, 'UTF-8');
        return $length >= $min && $length <= $max;
    }
    
    /**
     * Check for SQL injection patterns (additional layer of protection)
     * 
     * Examines input for common SQL injection patterns. This is a defense-in-depth
     * measure that should complement, not replace, prepared statements.
     * It helps identify potential attack attempts for logging/blocking.
     * 
     * @param string $input The input string to check for SQL injection patterns
     * @return bool True if suspicious patterns are detected, false otherwise
     */
    public function detectSqlInjection(string $input): bool
    {
        $sqlPatterns = [
            // SQL keywords that might indicate an injection attempt
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
            // Common SQL syntax characters often used in injections
            '/[\'";]/',
            // SQL comment syntax used to truncate queries
            '/--/',
            // Multi-line comment syntax
            '/\/\*.*\*\//',
            // Common OR/AND attack patterns
            '/\b(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i'
        ];
        
        // Check each pattern against the input
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true; // Suspicious pattern found
            }
        }
        
        return false; // No suspicious patterns detected
    }
    
    /**
     * Rate limiting check (basic implementation)
     * 
     * Implements a simple in-memory rate limiting mechanism to prevent abuse.
     * NOTE: This is a simplified implementation. For production use, consider
     * using Redis, Memcached, or a database to make this persistent across requests.
     * 
     * @param string $userId Identifier for the user or client being rate limited
     * @param string $action The action being rate limited (e.g., 'login', 'api_call')
     * @param int $maxAttempts Maximum number of attempts allowed within the time window
     * @param int $timeWindow Time window in seconds for the rate limit
     * @return bool True if the action is allowed, false if rate limited
     */
    public function checkRateLimit(string $userId, string $action, int $maxAttempts, int $timeWindow): bool
    {
        // In real implementation, this would use Redis or database
        // For testing, we'll use a simple static array
        static $rateLimits = [];
        
        // Create a unique key for this user+action combination
        $key = $userId . '_' . $action;
        $currentTime = time();
        
        // First request for this key - initialize counter
        if (!isset($rateLimits[$key])) {
            $rateLimits[$key] = ['count' => 1, 'start' => $currentTime];
            return true;
        }
        
        $data = $rateLimits[$key];
        
        // Reset counter if the time window has passed
        if ($currentTime - $data['start'] > $timeWindow) {
            $rateLimits[$key] = ['count' => 1, 'start' => $currentTime];
            return true;
        }
        
        // Check if the maximum attempts limit has been reached
        if ($data['count'] >= $maxAttempts) {
            return false; // Rate limit exceeded
        }
        
        // Increment the counter and allow the action
        $rateLimits[$key]['count']++;
        return true;
    }
    
    /**
     * Generate secure CSRF token
     * 
     * Creates a cryptographically secure random token for CSRF protection.
     * This token should be stored in the user's session and included in forms
     * to protect against Cross-Site Request Forgery attacks.
     * 
     * @return string A 64-character hexadecimal CSRF token
     */
    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32)); // 32 bytes = 64 hex characters
    }
    
    /**
     * Verify CSRF token
     * 
     * Compares a submitted CSRF token with the expected token from the session.
     * Uses a timing-safe comparison to prevent timing attacks.
     * 
     * @param string $token The token submitted with the request
     * @param string $sessionToken The token previously stored in the session
     * @return bool True if the tokens match, false otherwise
     */
    public function verifyCsrfToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token); // Constant-time comparison
    }
    
    /**
     * Validate password strength
     * 
     * Checks if a password meets minimum security requirements based on
     * industry best practices: minimum length and character diversity.
     * This helps protect against brute force and dictionary attacks.
     * 
     * @param string $password The password to validate
     * @return bool True if the password meets all requirements, false otherwise
     */
    public function validatePassword(string $password): bool
    {
        // At least 8 characters, contains uppercase, lowercase, number, and special character
        if (strlen($password) < 8) {
            return false; // Too short
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return false; // No uppercase letters
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return false; // No lowercase letters
        }
        
        if (!preg_match('/\d/', $password)) {
            return false; // No numbers
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false; // No special characters
        }
        
        return true; // Password meets all criteria
    }
    
    /**
     * Validate password strength with detailed error messages
     * 
     * Extended version of password validation that provides specific feedback
     * on why a password fails to meet requirements. This is useful for
     * user-friendly password creation forms that provide immediate feedback.
     * 
     * @param string $password The password to validate
     * @return array Array with 'valid' boolean and 'message' string explaining the result
     */
    public function validatePasswordWithMessage(string $password): array
    {
        $errors = [];
        
        // Check minimum length (8 characters)
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z)';
        }
        
        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z)';
        }
        
        // Check for at least one number
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number (0-9)';
        }
        
        // Check for at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character (e.g., !, @, #, $, %, ., etc.)';
        }
        
        // Check for common weak passwords that meet the above criteria but are still insecure
        $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty', 
            'abc123', 'password1', 'admin', 'letmein', 'welcome'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Password is too common. Please choose a more unique password';
        }
        
        // If no errors were found, the password is valid
        if (empty($errors)) {
            return [
                'valid' => true,
                'message' => 'Password meets all security requirements'
            ];
        }
        
        // Return detailed feedback on password requirements and specific issues
        return [
            'valid' => false,
            'message' => 'Your password should contain: ' . implode(', ', [
                'minimum 8 characters',
                '1 uppercase letter (A-Z)',
                '1 lowercase letter (a-z)', 
                '1 number (0-9)',
                '1 special character (e.g., !, @, #, $, %, .)'
            ]) . '. Issues found: ' . implode('; ', $errors)
        ];
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     * 
     * Ensures a string is a valid date in ISO format (YYYY-MM-DD).
     * This not only checks the format but also validates that the date actually exists.
     * 
     * @param string $date The date string to validate
     * @return bool True if the date is valid, false otherwise
     */
    public function isValidDate(string $date): bool
    {
        $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime && $dateTime->format('Y-m-d') === $date;
    }
    
    /**
     * Validate CSRF token
     * 
     * Simple validation for CSRF tokens, checking format and minimum security requirements.
     * NOTE: This is a minimal implementation. In production, always compare against
     * a stored session token using the verifyCsrfToken method.
     * 
     * @param string $token The CSRF token to validate
     * @return bool True if the token has valid format, false otherwise
     */
    public function validateCsrfToken(string $token): bool
    {
        // In real implementation, this would validate against session stored token
        // For testing, just check if token is not empty and has proper format
        return !empty($token) && ctype_alnum($token) && strlen($token) >= 32;
    }
    
    /**
     * Get security headers
     * 
     * Returns an array of recommended security headers to protect against
     * common web vulnerabilities like XSS, clickjacking, MIME sniffing, etc.
     * These headers should be added to all HTTP responses.
     * 
     * @return array Associative array of security headers and their values
     */
    public function getSecurityHeaders(): array
    {
        return [
            // Prevent MIME type sniffing (helps avoid MIME confusion attacks)
            'X-Content-Type-Options' => 'nosniff',
            
            // Prevent your page from being framed (anti-clickjacking)
            'X-Frame-Options' => 'DENY',
            
            // Enable browser's XSS protection features
            'X-XSS-Protection' => '1; mode=block',
            
            // Force HTTPS for this domain and subdomains
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            
            // Restrict which resources can be loaded
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';"
        ];
    }
    
    /**
     * Validate file upload
     * 
     * Performs security checks on uploaded files to prevent common vulnerabilities
     * such as unrestricted file uploads, path traversal, and server overload.
     * This should be used for all file uploads to ensure security.
     * 
     * @param array $file The file array from $_FILES
     * @param array $allowedTypes Array of allowed MIME types (e.g., ['image/jpeg', 'image/png'])
     * @param int $maxSize Maximum allowed file size in bytes
     * @return bool True if the file passes all security checks, false otherwise
     */
    public function validateFileUpload(array $file, array $allowedTypes, int $maxSize): bool
    {
        // Check if file was uploaded through HTTP POST and exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false; // Not a valid upload or upload failed
        }
        
        // Check if file type is in the allowed list (prevents malicious file types)
        if (!in_array($file['type'], $allowedTypes)) {
            return false; // Disallowed file type
        }
        
        // Check if file size is within limits (prevents DoS via large files)
        if ($file['size'] > $maxSize) {
            return false; // File too large
        }
        
        // Check for path traversal attempts in the filename
        // This prevents accessing files outside the intended directory
        if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false) {
            return false; // Potential path traversal attack
        }
        
        // All security checks passed
        return true;
    }
}
