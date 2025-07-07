<?php

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
class CleanupExpiredTokensCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetRequestRepository $repository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $removedCount = $this->repository->removeExpiredTokens();
        
        if ($removedCount > 0) {
            $io->success("Removed {$removedCount} expired password reset tokens.");
        } else {
            $io->info('No expired tokens found.');
        }

        return Command::SUCCESS;
    }
}
