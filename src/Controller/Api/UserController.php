<?php
/**
 * @fileoverview UserController - API Controller for User Management and Authentication
 * 
 * This controller provides comprehensive user management functionality including
 * user registration, authentication, password recovery, and administrative operations.
 * It handles user CRUD operations, role management, and email notifications.
 * 
 * Features:
 * - User registration and account creation
 * - Password recovery via email with secure tokens
 * - Admin user management (CRUD operations)
 * - Role-based access control (user/admin)
 * - Email notifications for account operations
 * - Secure password hashing and validation
 * 
 * API Endpoints:
 * - GET /api/users/me - Get current user profile
 * - POST /api/users/register - Register new user account
 * - POST /api/users/forgot-password - Request password reset
 * - GET /api/users - List all users (admin only)
 * - POST /api/users - Create user account (admin only)
 * - PUT /api/users/{id} - Update user account (admin only)
 * - DELETE /api/users/{id} - Delete user account (admin only)
 * - POST /api/users/{id}/reset-password - Send password reset (admin only)
 * - POST /api/users/reset-password-token/{token} - Reset password with token
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */
// src/Controller/Api/UserController.php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\PasswordResetRequest;
use App\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

/**
 * UserController - Handles user management and authentication operations
 * 
 * Provides comprehensive user management functionality including registration,
 * authentication, password recovery, and administrative operations. Implements
 * role-based access control and secure password handling.
 */
#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    /**
     * Constructor - Injects required services for user management
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     * @param UserPasswordHasherInterface $hasher Password hasher for secure password storage
     * @param MailerInterface $mailer Mailer service for email notifications
     * @param SecurityService $securityService Security service for input validation and sanitization
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
        private readonly SecurityService $securityService,
    ) {}

    /**
     * Get current authenticated user profile
     * 
     * Returns the profile information of the currently authenticated user
     * including basic details and role information.
     * 
     * @Route("/me", name="me", methods={"GET"})
     * @return JsonResponse User profile data with id, username, email, roles, and creation date
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Register a new user account
     * 
     * Creates a new user account with provided credentials. Validates required fields,
     * checks for email uniqueness, validates password strength, and securely hashes 
     * the password before storage. Includes protection against SQL injection and XSS attacks.
     * 
     * @Route("/register", name="register", methods={"POST"})
     * @param Request $req HTTP request containing username, email, and password
     * @return JsonResponse Success message or validation errors
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        // Parse and validate input data
        $data = json_decode($req->getContent(), true) ?: [];
        
        // Check for required fields
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }
        
        // Sanitize input data to prevent XSS attacks
        $username = $this->securityService->sanitizeInput($data['username']);
        $email = $this->securityService->sanitizeInput($data['email']);
        $password = $data['password']; // Don't sanitize password as it might contain special characters
        
        // Validate email format
        if (!$this->securityService->validateEmail($email)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }
        
        // Validate username length and format
        if (!$this->securityService->validateLength($username, 3, 50)) {
            return $this->json(['error' => 'Username must be between 3 and 50 characters'], 400);
        }
        
        // Validate password strength with detailed error messages
        $passwordValidation = $this->securityService->validatePasswordWithMessage($password);
        if (!$passwordValidation['valid']) {
            return $this->json(['error' => $passwordValidation['message']], 400);
        }
        
        // Check if email is already in use (using sanitized email)
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email in use'], 409);
        }
        
        // Check if username is already in use (using sanitized username)
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username already taken'], 409);
        }

        // Create new user with hashed password (using sanitized data)
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setRole('user');
        $user->setPassword(
            $this->hasher->hashPassword($user, $password)
        );

        // Persist user to database
        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'Registered'], 201);
    }

    /**
     * Request password reset via email
     * 
     * Generates a secure password reset token and sends an email with reset link.
     * Always returns success for security (doesn't reveal if email exists).
     * 
     * @Route("/forgot-password", name="forgot_password", methods={"POST"})
     * @param Request $req HTTP request containing email address
     * @return JsonResponse Success message regardless of email validity
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $req): JsonResponse
    {
        // Parse and validate email input
        $data = json_decode($req->getContent(), true) ?: [];
        if (empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], 400);
        }

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        
        // Always return success message for security (don't reveal if email exists)
        if (!$user) {
            return $this->json(['message' => 'The email with the link has been sent']);
        }

        // Create password reset request with secure token
        $pr = new PasswordResetRequest($user);
        $this->em->persist($pr);
        $this->em->flush();

        // Generate password reset link
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5177',
            $pr->getToken()
        );

        // Send password reset email
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@travelapp.com', $_ENV['MAILER_FROM_NAME'] ?? 'Travel App Support'))
            ->to($user->getEmail())
            ->subject('Password recovery')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'resetLink' => $resetLink,
            ]);

        $this->mailer->send($email);

        return $this->json(['message' => 'The letter with the link has been sent']);
    }

    /**
     * Create admin user account (admin only)
     * 
     * Creates a new user account with admin privileges. Requires admin authentication,
     * validates input data, checks password strength, and includes security protections
     * against SQL injection and XSS attacks before creating the account.
     * 
     * @Route("/create-admin", name="create_admin", methods={"POST"})
     * @param Request $req HTTP request containing admin user data
     * @return JsonResponse Success message or validation errors
     */
    #[Route('/create-admin', name: 'create_admin', methods: ['POST'])]
    public function createAdmin(Request $req): JsonResponse
    {
        // Ensure only admins can create admin accounts
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Parse and validate input data
        $data = json_decode($req->getContent(), true) ?: [];
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }
        
        // Sanitize input data to prevent XSS attacks
        $username = $this->securityService->sanitizeInput($data['username']);
        $email = $this->securityService->sanitizeInput($data['email']);
        $password = $data['password']; // Don't sanitize password as it might contain special characters
        
        // Validate email format
        if (!$this->securityService->validateEmail($email)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }
        
        // Validate username length and format
        if (!$this->securityService->validateLength($username, 3, 50)) {
            return $this->json(['error' => 'Username must be between 3 and 50 characters'], 400);
        }
        
        // Validate password strength with detailed error messages
        $passwordValidation = $this->securityService->validatePasswordWithMessage($password);
        if (!$passwordValidation['valid']) {
            return $this->json(['error' => $passwordValidation['message']], 400);
        }
        
        // Check if email is already in use (using sanitized email)
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email in use'], 409);
        }
        
        // Check if username is already in use (using sanitized username)
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username already taken'], 409);
        }

        // Create new admin user with hashed password (using sanitized data)
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setRole('admin');
        $user->setPassword(
            $this->hasher->hashPassword($user, $password)
        );

        // Persist admin user to database
        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'Admin created'], 201);
    }

    /**
     * List all users (admin only)
     * 
     * Returns a list of all registered users with their basic information.
     * Restricted to admin users only for privacy and security.
     * 
     * @Route("", name="list", methods={"GET"})
     * @return JsonResponse Array of user data objects
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Ensure only admins can view user list
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Fetch all users and format response data
        $users = $this->em->getRepository(User::class)->findAll();
        $data = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'email' => $u->getEmail(),
            'role' => $u->getRole(),
            'createdAt' => $u->getCreatedAt()->format('Y-m-d'),
        ], $users);

        return $this->json($data);
    }

    /**
     * Update user account (admin only)
     * 
     * Updates user account information including username, email, and role.
     * Validates role values and ensures only admins can modify accounts.
     * 
     * @Route("/{id}", name="update", methods={"PUT"})
     * @param int $id User ID to update
     * @param Request $req HTTP request containing updated user data
     * @return JsonResponse Success message or error if user not found
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $req): JsonResponse
    {
        // Ensure only admins can update user accounts
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Find user by ID
        $u = $this->em->find(User::class, $id) ?? null;
        if (!$u) return $this->json(['error' => 'Not found'], 404);

        // Parse update data and apply changes
        $d = json_decode($req->getContent(), true) ?? [];
        if (isset($d['username'])) $u->setUsername($d['username']);
        if (isset($d['email'])) $u->setEmail($d['email']);
        if (isset($d['role']) && \in_array($d['role'], ['user', 'admin'], true)) {
            $u->setRole($d['role']);
        }
        
        // Save changes to database
        $this->em->flush();

        return $this->json(['saved' => true]);
    }

    /**
     * Delete user account (admin only)
     * 
     * Permanently removes a user account from the system. This action is
     * irreversible and should be used with caution.
     * 
     * @Route("/{id}", name="delete", methods={"DELETE"})
     * @param int $id User ID to delete
     * @return JsonResponse Success message or error if user not found
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Ensure only admins can delete user accounts
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }
        
        // Remove user from database
        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted']);
    }

    /**
     * Send password reset email for specific user (admin only)
     * 
     * Generates a password reset token for a specific user and sends them
     * an email with reset instructions. Admin-only function for user management.
     * 
     * @Route("/{id}/reset-password", name="reset_password", methods={"POST"})
     * @param int $id User ID to send password reset for
     * @return JsonResponse Success message or error if user not found
     */
    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(int $id): JsonResponse
    {
        // Ensure only admins can trigger password resets for other users
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Create password reset request with secure token
        $pr = new PasswordResetRequest($user);
        $this->em->persist($pr);
        $this->em->flush();

        // Generate password reset link
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5177',
            $pr->getToken()
        );

        // Send password reset email
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@travelapp.com', $_ENV['MAILER_FROM_NAME'] ?? 'Travel App Support'))
            ->to($user->getEmail())
            ->subject('Password recovery')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'resetLink' => $resetLink,
            ]);

        $this->mailer->send($email);

        return $this->json(['message' => 'The email with the link has been sent']);
    }

    /**
     * Create new user account (admin only)
     * 
     * Creates a new user account with optional admin role assignment.
     * Generates a temporary password and sends welcome email with password setup link.
     * 
     * @Route("", name="create", methods={"POST"})
     * @param Request $req HTTP request containing new user data
     * @return JsonResponse User creation success with ID and role information
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        // Ensure only admins can create user accounts
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Parse and validate input data
        $d = json_decode($req->getContent(), true) ?? [];
        foreach (['username', 'email'] as $f) {
            if (empty($d[$f])) {
                return $this->json(["error" => "Missing $f"], 400);
            }
        }
        
        // Check if email is already in use
        if ($this->em->getRepository(User::class)
                     ->findOneBy(['email' => $d['email']])) {
            return $this->json(['error' => 'Email in use'], 409);
        }

        // Determine user role (default to 'user')
        $role = ($d['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        // Generate a secure random temporary password (will be replaced by reset)
        $tempPassword = bin2hex(random_bytes(16));
        
        // Create new user with temporary password
        $u = (new User())
            ->setUsername($d['username'])
            ->setEmail($d['email'])
            ->setRole($role);
        $u->setPassword($this->hasher->hashPassword($u, $tempPassword));

        $this->em->persist($u);
        $this->em->flush();

        // Create password reset request for the new user
        $pr = new PasswordResetRequest($u);
        $this->em->persist($pr);
        $this->em->flush();

        // Generate welcome email with password setup link
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5177',
            $pr->getToken()
        );

        // Send welcome email with password setup instructions
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@travelapp.com', $_ENV['MAILER_FROM_NAME'] ?? 'Travel App Support'))
            ->to($u->getEmail())
            ->subject('Welcome to Travel App - Set Your Password')
            ->htmlTemplate('emails/welcome_new_user.html.twig')
            ->context([
                'username' => $u->getUsername(),
                'resetLink' => $resetLink,
                'role' => $role,
                'isNewUser' => true,
            ]);

        $this->mailer->send($email);

        return $this->json([
            'id' => $u->getId(),
            'role' => $u->getRole(),
            'message' => 'User created successfully. Password setup email sent.'
        ], 201);
    }

    /**
     * Reset password using secure token
     * 
     * Completes the password reset process using a valid token from email.
     * Validates the token, checks password strength, updates the password, 
     * and removes the used token. Includes security protections.
     * 
     * @Route("/reset-password-token/{token}", name="reset_password_with_token", methods={"POST"})
     * @param string $token Password reset token from email
     * @param Request $req HTTP request containing new password
     * @return JsonResponse Success message or error for invalid/expired tokens
     */
    #[Route('/reset-password-token/{token}', name: 'reset_password_with_token', methods: ['POST'])]
    public function resetPasswordWithToken(string $token, Request $req): JsonResponse
    {
        // Parse and validate new password
        $data = json_decode($req->getContent(), true) ?: [];
        if (empty($data['password'])) {
            return $this->json(['error' => 'Missing password'], 400);
        }

        // Validate password strength with detailed error messages
        $passwordValidation = $this->securityService->validatePasswordWithMessage($data['password']);
        if (!$passwordValidation['valid']) {
            return $this->json(['error' => $passwordValidation['message']], 400);
        }

        // Find the password reset request using repository method
        $passwordResetRequest = $this->em->getRepository(PasswordResetRequest::class)
            ->findValidToken($token);

        if (!$passwordResetRequest) {
            return $this->json(['error' => 'Invalid or expired token'], 404);
        }

        // Reset the password with secure hashing
        $user = $passwordResetRequest->getUser();
        $hashedPassword = $this->hasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Remove the used token to prevent reuse
        $this->em->remove($passwordResetRequest);
        $this->em->flush();

        return $this->json(['message' => 'Password has been reset successfully']);
    }

}
