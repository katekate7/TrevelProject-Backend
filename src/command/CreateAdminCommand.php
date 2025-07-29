<?php
/**
 * Command for creating the first admin user in the application.
 * This is typically used during initial application setup or deployment.
 * 
 * Usage: php bin/console app:create-admin <username> <email> <password>
 * 
 * @package App\Command
 * @author Travel Project Team
 */

// src/Command/CreateAdminCommand.php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Bootstrap first admin user',
)]
/**
 * Console command to create an admin user for the travel application.
 * 
 * This command is essential for bootstrapping the application with an initial
 * admin user who can then manage the system and create other users/admins.
 * It includes validation to prevent duplicate admin accounts.
 */
class CreateAdminCommand extends Command
{
    /**
     * Constructor - Injects required services for user management.
     *
     * @param EntityManagerInterface $em - Doctrine entity manager for database operations
     * @param UserPasswordHasherInterface $hasher - Service for securely hashing passwords
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command arguments.
     * Defines what parameters the command accepts when executed.
     */
    protected function configure(): void
    {
        $this
            // Define command line arguments that must be provided
            ->addArgument('username', InputArgument::REQUIRED, 'Admin username')
            ->addArgument('email',    InputArgument::REQUIRED, 'Admin email')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password');
    }

    /**
     * Execute the command logic.
     * 
     * This method:
     * 1. Extracts arguments from command line input
     * 2. Validates that user doesn't already exist
     * 3. Creates new admin user with hashed password
     * 4. Persists user to database
     *
     * @param InputInterface $in - Command input interface
     * @param OutputInterface $out - Command output interface
     * @return int - Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        // Extract command arguments
        $username = $in->getArgument('username');
        $email    = $in->getArgument('email');
        $plainPwd = $in->getArgument('password');

        // Check if user with this email already exists to prevent duplicates
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $out->writeln('<error>User with this e-mail already exists</error>');
            return Command::FAILURE;
        }

        // Create new User entity with admin role
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setRole('admin');                // Important: specifically set as 'admin'

        // Hash the password securely before storing
        $user->setPassword(
            $this->hasher->hashPassword($user, $plainPwd)
        );

        // Persist the new admin user to database
        $this->em->persist($user);
        $this->em->flush();

        // Confirm successful creation
        $out->writeln('<info>Admin created:</info> '.$user->getEmail());
        return Command::SUCCESS;
    }
}
