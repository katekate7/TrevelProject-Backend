<?php
/**
 * Test suite for TripService functionality.
 * 
 * This test class validates all aspects of trip management including:
 * - Trip creation with valid and invalid data
 * - Trip updates and modifications
 * - Trip deletion with proper access control
 * - Data validation and error handling
 * - Integration with WeatherService
 * 
 * Uses PHPUnit mocking to isolate service behavior from dependencies.
 * 
 * @package App\Tests\Service
 * @author Travel Project Team
 */

namespace App\Tests\Service;

use App\Service\TripService;
use App\Service\WeatherService;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Comprehensive test suite for TripService class.
 * 
 * Tests cover all public methods of TripService with various scenarios
 * including edge cases, error conditions, and successful operations.
 */
class TripServiceTest extends TestCase
{
    /** @var TripService The service under test */
    private TripService $tripService;
    
    /** @var MockObject Mock of TripRepository for database operations */
    private MockObject $tripRepository;
    
    /** @var MockObject Mock of EntityManager for persistence operations */
    private MockObject $entityManager;
    
    /** @var MockObject Mock of WeatherService for weather integration */
    private MockObject $weatherService;

    /**
     * Set up test environment before each test.
     * 
     * Creates mock objects for all dependencies and initializes
     * the TripService with these mocks to ensure isolated testing.
     */
    protected function setUp(): void
    {
        // Create mock objects for all dependencies
        $this->tripRepository = $this->createMock(TripRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->weatherService = $this->createMock(WeatherService::class);
        
        // Initialize service with mocked dependencies
        $this->tripService = new TripService(
            $this->tripRepository,
            $this->entityManager,
            $this->weatherService
        );
    }

    /**
     * Test successful trip creation with valid data.
     * 
     * Verifies that:
     * - Trip entity is created correctly with provided data
     * - Entity is persisted to database via EntityManager
     * - Weather service is called to update trip weather
     * - All trip properties are set correctly
     */
    public function testCreateTripWithValidData(): void
    {
        // Arrange: Set up test user and valid trip data
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $tripData = [
            'city' => 'Paris',
            'country' => 'France',
            'startDate' => '2025-08-01',
            'endDate' => '2025-08-10'
        ];

        // Mock expectations: Verify persistence operations are called
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Trip::class));

        $this->entityManager->expects($this->once())->method('flush');
        
        // Mock expectations: Verify weather service integration
        $this->weatherService->expects($this->once())
            ->method('updateWeatherForTrip')
            ->with($this->isInstanceOf(Trip::class));

        // Act: Create the trip
        $result = $this->tripService->createTrip($user, $tripData);

        // Assert: Verify trip was created correctly with all properties
        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals('Paris', $result->getCity());
        $this->assertEquals('France', $result->getCountry());
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals('2025-08-01', $result->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-10', $result->getEndDate()->format('Y-m-d'));
    }

    /**
     * Test trip creation failure with invalid data.
     * 
     * Verifies that the service properly validates input data
     * and throws appropriate exceptions for invalid input.
     */
    public function testCreateTripWithInvalidData(): void
    {
        // Arrange: Set up test user and expect exception for empty data
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $this->expectException(\InvalidArgumentException::class);

        // Act & Assert: Attempt to create trip with empty data should fail
        $this->tripService->createTrip($user, []); // Empty data
    }

    /**
     * Test trip creation failure with malformed date format.
     * 
     * Verifies that invalid date strings are properly rejected
     * and appropriate exceptions are thrown.
     */
    public function testCreateTripWithInvalidDateFormat(): void
    {
        // Arrange: Set up test user and trip data with invalid date
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $invalidTripData = [
            'city' => 'Paris',
            'country' => 'France',
            'startDate' => 'invalid-date',
            'endDate' => '2025-08-10'
        ];

        $this->expectException(\Exception::class);

        // Act & Assert: Invalid date format should throw exception
        $this->tripService->createTrip($user, $invalidTripData);
    }

    /**
     * Test successful trip update with valid modification data.
     * 
     * Verifies that:
     * - Existing trip properties can be updated
     * - Only provided fields are modified
     * - Changes are persisted to database
     * - Original unmodified data remains intact
     */
    public function testUpdateTripWithValidData(): void
    {
        // Arrange: Create existing trip and update data
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Paris')
             ->setCountry('France')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $updateData = [
            'startDate' => '2025-08-05',
            'endDate' => '2025-08-15'
        ];

        // Mock expectations: Verify flush is called to persist changes
        $this->entityManager->expects($this->once())->method('flush');

        // Act: Update the trip
        $result = $this->tripService->updateTrip($trip, $updateData);

        // Assert: Verify updated fields and unchanged fields
        $this->assertEquals('2025-08-05', $result->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-15', $result->getEndDate()->format('Y-m-d'));
        $this->assertEquals('Paris', $result->getCity()); // Unchanged
        $this->assertEquals('France', $result->getCountry()); // Unchanged
    }

    /**
     * Test successful trip deletion with valid ID and access rights.
     * 
     * Verifies that:
     * - Trip is found by ID
     * - User owns the trip (access control)
     * - Trip is removed from database
     * - Changes are persisted
     */
    public function testDeleteTripWithValidId(): void
    {
        // Arrange: Set up user and trip for deletion
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Paris')
             ->setCountry('France')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        // Mock expectations: Repository should find the trip
        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($trip);

        // Mock expectations: EntityManager should remove and persist changes
        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($trip);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Act: Delete the trip
        $result = $this->tripService->deleteTrip(1, $user);

        // Assert: Deletion should succeed
        $this->assertTrue($result);
    }

    /**
     * Test trip deletion failure when trip doesn't exist.
     * 
     * Verifies that appropriate exception is thrown when
     * attempting to delete a non-existent trip.
     */
    public function testDeleteTripNotFound(): void
    {
        // Arrange: Set up user and mock repository to return null
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // Expect specific exception for trip not found
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trip not found');

        // Act & Assert: Attempt to delete non-existent trip should fail
        $this->tripService->deleteTrip(999, $user);
    }

    /**
     * Test trip deletion failure due to access control.
     * 
     * Verifies that users cannot delete trips that don't belong to them.
     * This is a critical security test for access control.
     */
    public function testDeleteTripAccessDenied(): void
    {
        // Arrange: Create two different users
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setUsername('user1');

        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setUsername('user2');
        
        // Trip belongs to user2
        $trip = new Trip();
        $trip->setUser($user2);

        // Mock repository to return the trip
        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($trip);

        // Expect access denied exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Access denied');

        // Act & Assert: user1 tries to delete user2's trip (should fail)
        $this->tripService->deleteTrip(1, $user1);
    }

    /**
     * Test trip creation with various valid input combinations.
     * 
     * Uses a data provider to test multiple valid trip scenarios
     * ensuring the service handles different cities, countries, and dates correctly.
     * 
     * @dataProvider validTripDataProvider
     */
    public function testCreateTripWithVariousValidInputs(array $tripData): void
    {
        // Arrange: Set up test user and mock expectations
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        // Mock expectations for successful creation
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->weatherService->expects($this->once())->method('updateWeatherForTrip');

        // Act: Create trip with provided test data
        $result = $this->tripService->createTrip($user, $tripData);

        // Assert: Verify trip was created with correct properties
        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals($tripData['city'], $result->getCity());
        $this->assertEquals($tripData['country'], $result->getCountry());
    }

    /**
     * Test trip creation failure with various invalid input combinations.
     * 
     * Uses a data provider to test multiple invalid trip scenarios
     * ensuring proper validation and exception handling for edge cases.
     * 
     * @dataProvider invalidTripDataProvider
     */
    public function testCreateTripWithVariousInvalidInputs(array $tripData, string $expectedException): void
    {
        // Arrange: Set up test user and expect specified exception
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->expectException($expectedException);

        // Act & Assert: Invalid data should throw appropriate exception
        $this->tripService->createTrip($user, $tripData);
    }

    /**
     * Data provider for valid trip creation test cases.
     * 
     * Provides various combinations of valid trip data to ensure
     * the service handles different destinations and date ranges correctly.
     * 
     * @return array Array of valid trip data sets for testing
     */
    public static function validTripDataProvider(): array
    {
        return [
            'Paris trip' => [
                ['city' => 'Paris', 'country' => 'France', 'startDate' => '2025-08-01', 'endDate' => '2025-08-10']
            ],
            'London trip' => [
                ['city' => 'London', 'country' => 'United Kingdom', 'startDate' => '2025-09-15', 'endDate' => '2025-09-20']
            ],
            'Tokyo trip' => [
                ['city' => 'Tokyo', 'country' => 'Japan', 'startDate' => '2025-10-01', 'endDate' => '2025-10-07']
            ],
        ];
    }

    /**
     * Data provider for invalid trip creation test cases.
     * 
     * Provides various combinations of invalid trip data to ensure
     * proper validation and error handling for edge cases and malformed input.
     * 
     * @return array Array of invalid trip data sets and expected exceptions
     */
    public static function invalidTripDataProvider(): array
    {
        return [
            'invalid start date' => [
                ['city' => 'Paris', 'country' => 'France', 'startDate' => 'invalid', 'endDate' => '2025-08-10'],
                \Exception::class
            ],
            'invalid end date' => [
                ['city' => 'Berlin', 'country' => 'Germany', 'startDate' => '2025-08-01', 'endDate' => 'bad-date'],
                \Exception::class
            ],
            'empty city' => [
                ['city' => '', 'country' => 'France', 'startDate' => '2025-08-01', 'endDate' => '2025-08-10'],
                \InvalidArgumentException::class
            ],
            'missing country' => [
                ['city' => 'Paris', 'startDate' => '2025-08-01', 'endDate' => '2025-08-10'],
                \InvalidArgumentException::class
            ],
            'end date before start date' => [
                ['city' => 'Rome', 'country' => 'Italy', 'startDate' => '2025-08-15', 'endDate' => '2025-08-10'],
                \Exception::class
            ],
            'completely empty data' => [
                [],
                \InvalidArgumentException::class
            ],
        ];
    }
}
