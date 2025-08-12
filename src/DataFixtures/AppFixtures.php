<?php
/**
 * App Fixtures for loading initial data
 * 
 * This class provides data fixtures for setting up the database with
 * initial data needed for development and testing environments.
 * 
 * @package App\DataFixtures
 * @author Travel Project Team
 */

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * AppFixtures class for loading test data
 * 
 * This class creates initial database records for development and testing,
 * particularly setting up admin user accounts with proper credentials.
 */
class AppFixtures extends Fixture
{
    /**
     * Password hasher service for securely hashing user passwords
     * 
     * @var UserPasswordHasherInterface
     */
    private $hasher;

    /**
     * Constructor to inject the password hasher service
     * 
     * @param UserPasswordHasherInterface $hasher Service to hash passwords securely
     */
    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * Load method called by Doctrine to populate database with fixtures
     * 
     * Creates an admin user with predefined credentials for development purposes.
     * In production, these default credentials should be changed.
     *
     * @param ObjectManager $manager Entity manager to persist the fixtures
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@example.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $user->setRole('admin');
        $user->setCreatedAt(new \DateTimeImmutable()); // Set the value for createdAt
    
        // Save the user to the database
        $manager->persist($user);
        $manager->flush();
    }
    
}

