<?php
/**
 * Command for creating admin users via command line interface.
 * This command provides a secure way to create administrative accounts
 * without requiring web interface access.
 * 
 * Usage: php bin/console app:create-admin
 * 
 * @package App\Command
 * @author Travel Project Team
 */

namespace App\Command;

use App\Entity\User;
use App\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create a new admin user'
)]
class CreateAdminCommand extends Command
{
    /**
     * Entity manager for database operations
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * Password hasher service for securely hashing user passwords
     */
    private UserPasswordHasherInterface $passwordHasher;
    
    /**
     * Security service for validating user inputs
     */
    private SecurityService $securityService;

    /**
     * Command constructor
     * 
     * Injects all the dependencies needed for creating users
     * 
     * @param EntityManagerInterface $entityManager For database operations
     * @param UserPasswordHasherInterface $passwordHasher For secure password hashing
     * @param SecurityService $securityService For input validation
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SecurityService $securityService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->securityService = $securityService;
    }

    /**
     * Configure the command
     * 
     * Defines the command name, arguments, and help text
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Create a new admin user account')
            ->addArgument('username', InputArgument::REQUIRED, 'Admin username')
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password')
            ->setHelp('This command allows you to create a new admin user account with the specified credentials.');
    }

    /**
     * Execute the command
     * 
     * This is the main method that runs when the command is executed
     * It handles all the logic for creating a new admin user
     * 
     * @param InputInterface $input Command input (arguments and options)
     * @param OutputInterface $output Command output for display
     * @return int Command success or failure status code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create SymfonyStyle for nicer command line output formatting
        $io = new SymfonyStyle($input, $output);
        
        // Get command arguments
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Validate input - check that all provided data meets security requirements
        
        // Verify email format is valid
        if (!$this->securityService->validateEmail($email)) {
            $io->error('Invalid email format.');
            return Command::FAILURE;
        }

        // Ensure username is an appropriate length
        if (!$this->securityService->validateLength($username, 3, 50)) {
            $io->error('Username must be between 3 and 50 characters.');
            return Command::FAILURE;
        }

        // Check password meets complexity requirements
        $passwordValidation = $this->securityService->validatePasswordWithMessage($password);
        if (!$passwordValidation['valid']) {
            $io->error($passwordValidation['message']);
            return Command::FAILURE;
        }

        // Check if user already exists - prevent duplicate accounts
        
        // Check for existing user with the same email
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('A user with this email already exists.');
            return Command::FAILURE;
        }

        // Check for existing user with the same username
        $existingUsername = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUsername) {
            $io->error('A user with this username already exists.');
            return Command::FAILURE;
        }

        try {
            // Create admin user - add new user to the database
            $user = new User();
            $user->setUsername($username);  // Set user's username
            $user->setEmail($email);        // Set user's email
            $user->setRole('admin');        // Give admin privileges
            
            // Hash the password for security (never store plain text passwords)
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Save the new user to database
            $this->entityManager->persist($user);  // Queue the user for saving
            $this->entityManager->flush();         // Execute the database operation

            // Show success message with the username
            $io->success(sprintf('Admin user "%s" has been created successfully!', $username));
            return Command::SUCCESS;  // Return success status code

        } catch (\Exception $e) {
            // Handle any errors during user creation
            $io->error('An error occurred while creating the admin user: ' . $e->getMessage());
            return Command::FAILURE;  // Return failure status code
        }
    }
}
