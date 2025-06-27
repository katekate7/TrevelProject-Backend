<?php
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
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Admin username')
            ->addArgument('email',    InputArgument::REQUIRED, 'Admin email')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $username = $in->getArgument('username');
        $email    = $in->getArgument('email');
        $plainPwd = $in->getArgument('password');

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $out->writeln('<error>User with this e-mail already exists</error>');
            return Command::FAILURE;
        }

        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setRole('admin');                // важливо: саме 'admin'

        $user->setPassword(
            $this->hasher->hashPassword($user, $plainPwd)
        );

        $this->em->persist($user);
        $this->em->flush();

        $out->writeln('<info>Admin created:</info> '.$user->getEmail());
        return Command::SUCCESS;
    }
}
