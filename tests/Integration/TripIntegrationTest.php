<?php

namespace App\Tests\Integration;

use App\Entity\Trip;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class TripIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testCreateAndRetrieveTrip(): void
    {
        // Create a test user
        $user = new User();
        $user->setEmail('integration@test.com');
        $user->setUsername('testuser');
        $user->setPassword('$2y$13$hashedpassword'); // Hashed password
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Create a trip
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Integration Test City')
             ->setCountry('Test Country')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        // Clear the entity manager to ensure we're fetching from database
        $this->entityManager->clear();

        // Retrieve and verify
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $savedTrip = $tripRepository->find($trip->getId());
        
        $this->assertNotNull($savedTrip);
        $this->assertEquals('Integration Test City', $savedTrip->getCity());
        $this->assertEquals('Test Country', $savedTrip->getCountry());
        $this->assertEquals('2025-08-01', $savedTrip->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-10', $savedTrip->getEndDate()->format('Y-m-d'));
        $this->assertEquals($user->getId(), $savedTrip->getUser()->getId());
    }

    public function testUserTripRelationship(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('relationship@test.com');
        $user->setUsername('relationuser');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setRole('user'); // Fixed: use setRole() instead of setRoles()
        
        $this->entityManager->persist($user);

        // Create multiple trips for the user
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

        $this->entityManager->persist($trip1);
        $this->entityManager->persist($trip2);
        $this->entityManager->flush();

        $this->entityManager->clear();

        // Test finding trips by user
        $tripRepository = $this->entityManager->getRepository(Trip::class);
        $userRepository = $this->entityManager->getRepository(User::class);
        
        $savedUser = $userRepository->find($user->getId());
        $userTrips = $tripRepository->findBy(['user' => $savedUser]);
        
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
