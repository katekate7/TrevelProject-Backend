<?php
/**
 * Integration test suite for Trip entity database operations.
 * 
 * This test class validates trip-related database operations including:
 * - Trip creation and persistence
 * - Trip retrieval and data integrity
 * - User-Trip relationship management
 * - Database consistency and entity relationships
 * 
 * Uses KernelTestCase for database integration testing with Doctrine ORM.
 * 
 * @package App\Tests\Integration
 * @author Travel Project Team
 */

namespace App\Tests\Integration;

use App\Entity\Trip;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Integration tests for Trip entity database operations.
 * 
 * Tests focus on database persistence, entity relationships,
 * and data integrity for trip-related operations.
 */
class TripIntegrationTest extends KernelTestCase
{
    /** @var EntityManagerInterface Database entity manager for test operations */
    private EntityManagerInterface $entityManager;

    /**
     * Set up test environment before each test.
     * 
     * Boots the Symfony kernel and initializes the entity manager
     * for database operations during testing.
     */
    protected function setUp(): void
    {
        // Boot Symfony kernel and get entity manager from container
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * Test complete trip creation and retrieval workflow.
     * 
     * Verifies that:
     * - Trip can be created and persisted to database
     * - All trip properties are saved correctly
     * - Trip can be retrieved with correct data
     * - User-Trip relationship is maintained
     * - Database operations work end-to-end
     */
    public function testCreateAndRetrieveTrip(): void
    {
        // Arrange: Create and persist test user
        $user = new User();
        $user->setEmail('integration@test.com');
        $user->setUsername('testuser');
        $user->setPassword('$2y$13$hashedpassword'); // Hashed password
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Arrange: Create trip entity with all required properties
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Integration Test City')
             ->setCountry('Test Country')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        // Act: Persist trip to database
        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        // Clear entity manager to ensure fresh database fetch
        $this->entityManager->clear();

        // Act: Retrieve trip from database
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $savedTrip = $tripRepository->find($trip->getId());
        
        // Assert: Verify all trip data was saved and retrieved correctly
        $this->assertNotNull($savedTrip);
        $this->assertEquals('Integration Test City', $savedTrip->getCity());
        $this->assertEquals('Test Country', $savedTrip->getCountry());
        $this->assertEquals('2025-08-01', $savedTrip->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-10', $savedTrip->getEndDate()->format('Y-m-d'));
        $this->assertEquals($user->getId(), $savedTrip->getUser()->getId());
    }

    /**
     * Test User-Trip relationship and multiple trip handling.
     * 
     * Verifies that:
     * - User can have multiple trips
     * - Trip-User relationship is bidirectional
     * - Database queries work correctly for relationships
     * - Data integrity is maintained across entities
     */
    public function testUserTripRelationship(): void
    {
        // Arrange: Create and persist test user
        $user = new User();
        $user->setEmail('relationship@test.com');
        $user->setUsername('relationuser');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);

        // Arrange: Create multiple trips for the same user
        $trip1 = new Trip();
        $trip1->setUser($user)
              ->setCity('Paris')
              ->setCountry('France')
              ->setStartDate(new \DateTimeImmutable('2025-08-01'))
              ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $trip2 = new Trip();
        $trip2->setUser($user)
              ->setCity('London')
              ->setCountry('UK')
              ->setStartDate(new \DateTimeImmutable('2025-09-01'))
              ->setEndDate(new \DateTimeImmutable('2025-09-10'));

        // Act: Persist all entities
        $this->entityManager->persist($trip1);
        $this->entityManager->persist($trip2);
        $this->entityManager->flush();

        // Clear entity manager to ensure fresh database queries
        $this->entityManager->clear();

        // Act: Test relationship queries
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $userRepository = $this->entityManager->getRepository(User::class);
        
        $savedUser = $userRepository->find($user->getId());
        $userTrips = $tripRepository->findBy(['user' => $savedUser]);
        
        // Assert: Verify relationship integrity and trip count
        $this->assertCount(2, $userTrips);
        
        // Verify both trips exist
        $cities = array_map(fn($trip) => $trip->getCity(), $userTrips);
        $this->assertContains('Paris', $cities);
        $this->assertContains('London', $cities);
    }

    public function testTripUpdate(): void
    {
        // Create user and trip
        $user = new User();
        $user->setEmail('update@test.com');
        $user->setUsername('updateuser');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);

        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Original City')
             ->setCountry('Original Country')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        $tripId = $trip->getId();

        // Update the trip
        $trip->setCity('Updated City');
        $trip->setStartDate(new \DateTimeImmutable('2025-08-05'));
        
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Verify updates
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $updatedTrip = $tripRepository->find($tripId);
        
        $this->assertEquals('Updated City', $updatedTrip->getCity());
        $this->assertEquals('Original Country', $updatedTrip->getCountry()); // Should remain unchanged
        $this->assertEquals('2025-08-05', $updatedTrip->getStartDate()->format('Y-m-d'));
    }

    public function testTripDeletion(): void
    {
        // Create user and trip
        $user = new User();
        $user->setEmail('delete@test.com');
        $user->setUsername('deleteuser');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);

        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('To Be Deleted')
             ->setCountry('Delete Country')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        $tripId = $trip->getId();

        // Delete the trip
        $this->entityManager->remove($trip);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Verify deletion
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $deletedTrip = $tripRepository->find($tripId);
        $this->assertNull($deletedTrip);
    }

    public function testTripQueryOrdering(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('query@test.com');
        $user->setUsername('queryuser');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);

        // Create multiple trips with different dates
        $dates = [
            '2025-08-01',
            '2025-08-05',
            '2025-08-03',
        ];

        $trips = [];
        foreach ($dates as $i => $date) {
            $trip = new Trip();
            $trip->setUser($user)
                 ->setCity("City " . ($i + 1))
                 ->setCountry("Country " . ($i + 1))
                 ->setStartDate(new \DateTimeImmutable($date))
                 ->setEndDate(new \DateTimeImmutable($date));

            $this->entityManager->persist($trip);
            $trips[] = $trip;
        }
        
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Test ordering by date ascending
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $savedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        
        $tripsAsc = $tripRepository->findBy(
            ['user' => $savedUser], 
            ['startDate' => 'ASC']
        );

        $this->assertCount(3, $tripsAsc);
        $this->assertEquals('2025-08-01', $tripsAsc[0]->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-03', $tripsAsc[1]->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-05', $tripsAsc[2]->getStartDate()->format('Y-m-d'));

        // Test ordering by date descending
        $tripsDesc = $tripRepository->findBy(
            ['user' => $savedUser], 
            ['startDate' => 'DESC']
        );

        $this->assertEquals('2025-08-05', $tripsDesc[0]->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-03', $tripsDesc[1]->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-01', $tripsDesc[2]->getStartDate()->format('Y-m-d'));
    }

    protected function tearDown(): void
    {
        // Clean up database - but only if entity manager exists
        if (isset($this->entityManager)) {
            try {
                $this->entityManager->createQuery('DELETE FROM App\Entity\Trip')->execute();
                $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
            
            $this->entityManager->close();
        }
        
        parent::tearDown();
        // Don't set to null - PHP 8+ doesn't allow null for typed properties
    }
}
