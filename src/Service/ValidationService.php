<?php

namespace App\Service;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * Service for comprehensive input validation and sanitization
 */
class ValidationService
{
    public function __construct()
    {
        $this->validator = Validation::createValidator();
    }
    
    /**
     * Sanitizes text input by removing unwanted characters
     */
    public function sanitizeText(string $text): string
    {
        // Remove any HTML/script tags
        $sanitized = strip_tags($text);
        // Trim whitespace
        $sanitized = trim($sanitized);
        return $sanitized;
    }
    
    /**
     * Validate email address format
     */
    public function validateEmail(string $email): array
    {
        $constraint = new Assert\Email([
            'message' => 'The email "{{ value }}" is not a valid email.',
            'mode' => 'strict',
        ]);
        
        $violations = $this->validator->validate($email, $constraint);
        $errors = [];
        
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate password strength 
     */
    public function validatePassword(string $password): array
    {
        $errors = [];
        
        // Check length (minimum 8 characters)
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        // Check for uppercase letters
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        // Check for lowercase letters
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        // Check for numbers
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        // Check for special characters
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        return $errors;
    }
    
    /**
     * Validate user input array against specified rules
     */
    public function validateArray(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            // Check if required field exists
            if (isset($rule['required']) && $rule['required'] && (!isset($data[$field]) || $data[$field] === '')) {
                $errors[$field][] = "The $field field is required.";
                continue;
            }
            
            // Skip validation if field is not present and not required
            if (!isset($data[$field])) {
                continue;
            }
            
            // Validate field based on its type
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        $emailErrors = $this->validateEmail($data[$field]);
                        if (!empty($emailErrors)) {
                            $errors[$field] = $emailErrors;
                        }
                        break;
                    case 'password':
                        $passwordErrors = $this->validatePassword($data[$field]);
                        if (!empty($passwordErrors)) {
                            $errors[$field] = $passwordErrors;
                        }
                        break;
                    case 'string':
                        if (isset($rule['min']) && strlen($data[$field]) < $rule['min']) {
                            $errors[$field][] = "The $field must be at least {$rule['min']} characters.";
                        }
                        if (isset($rule['max']) && strlen($data[$field]) > $rule['max']) {
                            $errors[$field][] = "The $field must be at most {$rule['max']} characters.";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($data[$field])) {
                            $errors[$field][] = "The $field must be a number.";
                        }
                        break;
                    case 'date':
                        $date = \DateTime::createFromFormat('Y-m-d', $data[$field]);
                        if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                            $errors[$field][] = "The $field must be a valid date in format YYYY-MM-DD.";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
}
