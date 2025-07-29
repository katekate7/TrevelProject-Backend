<?php
/**
 * Command for cleaning up expired password reset tokens from the database.
 * This maintenance command should be run periodically (e.g., via cron)
 * to keep the database clean and secure.
 * 
 * Usage: php bin/console app:cleanup-expired-tokens
 * 
 * @package App\Command
 * @author Travel Project Team
 */

namespace App\Command;

use App\Repository\PasswordResetRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-tokens',
    description: 'Remove expired password reset tokens',
)]
/**
 * Console command to remove expired password reset tokens.
 * 
 * This maintenance command helps keep the database clean by removing
 * password reset tokens that have passed their expiration time.
 * Running this regularly prevents the password_reset_requests table
 * from growing indefinitely.
 */
class CleanupExpiredTokensCommand extends Command
{
    /**
     * Constructor - Injects required services for token cleanup.
     *
     * @param EntityManagerInterface $em - Doctrine entity manager for database operations
     * @param PasswordResetRequestRepository $repository - Repository for password reset requests
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetRequestRepository $repository
    ) {
        parent::__construct();
    }

    /**
     * Execute the cleanup command.
     * 
     * This method:
     * 1. Calls the repository method to remove expired tokens
     * 2. Reports how many tokens were removed
     * 3. Provides appropriate user feedback
     *
     * @param InputInterface $input - Command input interface
     * @param OutputInterface $output - Command output interface
     * @return int - Command::SUCCESS (always succeeds)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create styled output for better user experience
        $io = new SymfonyStyle($input, $output);

        // Remove expired tokens and get count of removed items
        $removedCount = $this->repository->removeExpiredTokens();
        
        // Provide feedback to user based on results
        if ($removedCount > 0) {
            $io->success("Removed {$removedCount} expired password reset tokens.");
        } else {
            $io->info('No expired tokens found.');
        }

        return Command::SUCCESS;
    }
}
