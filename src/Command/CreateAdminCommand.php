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
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private SecurityService $securityService;

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

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new admin user account')
            ->addArgument('username', InputArgument::REQUIRED, 'Admin username')
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password')
            ->setHelp('This command allows you to create a new admin user account with the specified credentials.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Validate input
        if (!$this->securityService->validateEmail($email)) {
            $io->error('Invalid email format.');
            return Command::FAILURE;
        }

        if (!$this->securityService->validateLength($username, 3, 50)) {
            $io->error('Username must be between 3 and 50 characters.');
            return Command::FAILURE;
        }

        $passwordValidation = $this->securityService->validatePasswordWithMessage($password);
        if (!$passwordValidation['valid']) {
            $io->error($passwordValidation['message']);
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('A user with this email already exists.');
            return Command::FAILURE;
        }

        $existingUsername = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUsername) {
            $io->error('A user with this username already exists.');
            return Command::FAILURE;
        }

        try {
            // Create admin user
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setRole('admin');
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(sprintf('Admin user "%s" has been created successfully!', $username));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('An error occurred while creating the admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
