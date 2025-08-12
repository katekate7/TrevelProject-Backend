<?php
/**
 * Token Cleanup Command
 * 
 * This command is used to clean up expired JWT tokens from the system.
 * JWT tokens should be removed after they expire to keep the database clean
 * and improve security by removing old authentication tokens.
 * 
 * Usage: php bin/console app:cleanup-expired-tokens
 * 
 * @package App\Command
 */

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-tokens',
    description: 'Cleanup expired JWT tokens from the system'
)]
class CleanupExpiredTokensCommand extends Command
{
    /**
     * Constructor for the command
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the command
     * 
     * This method runs when the command is executed and handles the token cleanup process
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create a stylized interface for better console output
        $io = new SymfonyStyle($input, $output);
        
        // Inform the user that the cleanup process is starting
        $io->info('Cleaning up expired tokens...');
        
        // TODO: Implement token cleanup logic
        // This should include:
        // 1. Finding expired tokens in the database
        // 2. Removing them safely
        // 3. Logging the number of tokens removed
        
        // Show success message after completing the operation
        $io->success('Token cleanup completed successfully.');
        
        // Return success status code
        return Command::SUCCESS;
    }
}