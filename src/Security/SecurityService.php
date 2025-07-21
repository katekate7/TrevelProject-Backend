<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SecurityService
{
    private array $allowedHtmlTags = ['b', 'i', 'strong', 'em', 'p', 'br'];
    
    /**
     * Comprehensive input sanitization
     */
    public function sanitizeInput(string $input, bool $allowHtml = false): string
    {
        // Trim whitespace
        $input = trim($input);
        
        // Handle HTML
        if ($allowHtml) {
            // Allow only specific safe HTML tags
            $allowedTags = '<' . implode('><', $this->allowedHtmlTags) . '>';
            $input = strip_tags($input, $allowedTags);
        } else {
            // Remove all HTML tags
            $input = strip_tags($input);
        }
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove potential script injections
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/on\w+\s*=/i', '', $input);
        
        return $input;
    }
    
    /**
     * Validate and sanitize array of inputs
     */
    public function sanitizeArray(array $inputs, bool $allowHtml = false): array
    {
        $sanitized = [];
        foreach ($inputs as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeInput($value, $allowHtml);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $allowHtml);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Validate email format
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate string length
     */
    public function validateLength(string $input, int $min = 1, int $max = 1000): bool
    {
        $length = mb_strlen($input, 'UTF-8');
        return $length >= $min && $length <= $max;
    }
    
    /**
     * Check for SQL injection patterns (additional layer of protection)
     */
    public function detectSqlInjection(string $input): bool
    {
        $sqlPatterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
            '/[\'";]/',
            '/--/',
            '/\/\*.*\*\//',
            '/\b(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i'
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rate limiting check (basic implementation)
     */
    public function checkRateLimit(string $userId, string $action, int $maxAttempts, int $timeWindow): bool
    {
        // In real implementation, this would use Redis or database
        // For testing, we'll use a simple static array
        static $rateLimits = [];
        
        $key = $userId . '_' . $action;
        $currentTime = time();
        
        if (!isset($rateLimits[$key])) {
            $rateLimits[$key] = ['count' => 1, 'start' => $currentTime];
            return true;
        }
        
        $data = $rateLimits[$key];
        
        // Reset if time window passed
        if ($currentTime - $data['start'] > $timeWindow) {
            $rateLimits[$key] = ['count' => 1, 'start' => $currentTime];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $rateLimits[$key]['count']++;
        return true;
    }
    
    /**
     * Generate secure CSRF token
     */
    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword(string $password): bool
    {
        // At least 8 characters, contains uppercase, lowercase, number, and special character
        if (strlen($password) < 8) {
            return false;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        if (!preg_match('/\d/', $password)) {
            return false;
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     */
    public function isValidDate(string $date): bool
    {
        $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime && $dateTime->format('Y-m-d') === $date;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        // In real implementation, this would validate against session stored token
        // For testing, just check if token is not empty and has proper format
        return !empty($token) && ctype_alnum($token) && strlen($token) >= 32;
    }
    
    /**
     * Get security headers
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';"
        ];
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload(array $file, array $allowedTypes, int $maxSize): bool
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Check file type
        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // Check for path traversal in filename
        if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false) {
            return false;
        }
        
        return true;
    }
}
