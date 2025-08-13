<?php
namespace App\Tests\Integration;

use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TripIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Boot the real Symfony application kernel
        // This gives us access to the real service container and Doctrine EntityManager
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    public function testCreateAndFetchTrip(): void
    {
        /**
         * Step 1: Create and persist a User
         * - We give it a unique email/username so the test won't conflict with existing DB data
         * - Password can be any bcrypt-like string (no need for real hashing here)
         * - Role is set to 'user' for simplicity
         */
        $user = (new User())
            ->setEmail('it_' . uniqid() . '@test.dev')
            ->setUsername('it_' . uniqid())
            ->setPassword('$2y$13$dummyhashdummyhashdummyhashdu')
            ->setRole('user');

        $this->em->persist($user);
        $this->em->flush(); // Actually writes the User into the test database

        /**
         * Step 2: Create and persist a Trip linked to that User
         * - Demonstrates the relationship between Trip and User
         * - We set city, country, and date range for the trip
         */
        $trip = (new Trip())
            ->setUser($user)
            ->setCity('Test City')
            ->setCountry('Testland')
            ->setStartDate(new \DateTimeImmutable('2025-08-01'))
            ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $this->em->persist($trip);
        $this->em->flush(); // Saves the Trip into the DB
        $tripId = $trip->getId(); // Store the ID for fetching later

        /**
         * Step 3: Clear the EntityManager
         * - This removes all in-memory entities so the next fetch
         *   will hit the database (true integration test)
         */
        $this->em->clear();

        /**
         * Step 4: Fetch the Trip from the database by ID
         * - Ensures that the data was actually persisted correctly
         * - Also verifies that the Trip → User relationship works
         */
        /** @var Trip|null $fresh */
        $fresh = $this->em->getRepository(Trip::class)->find($tripId);

        // Assertions to confirm everything is as expected
        $this->assertNotNull($fresh, 'Trip should be persisted and retrievable from DB');
        $this->assertSame('Test City', $fresh->getCity());
        $this->assertSame('Testland', $fresh->getCountry());
        $this->assertSame('2025-08-01', $fresh->getStartDate()->format('Y-m-d'));
        $this->assertSame('2025-08-10', $fresh->getEndDate()->format('Y-m-d'));
        $this->assertSame(
            $user->getId(),
            $fresh->getUser()->getId(),
            'Trip must belong to the correct User'
        );
    }

    protected function tearDown(): void
    {
        // Close the EntityManager to prevent memory leaks between tests
        if (isset($this->em)) {
            $this->em->close();
        }
        parent::tearDown();
    }
}
