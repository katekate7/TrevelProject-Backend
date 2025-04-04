<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@example.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $user->setRole('admin');
        $user->setCreatedAt(new \DateTimeImmutable()); // Встановлюємо значення для createdAt
    
        $manager->persist($user);
        $manager->flush();
    }
    
}

