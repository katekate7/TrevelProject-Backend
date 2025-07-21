<?php

namespace App\Tests\Service;

use App\Service\TripService;
use App\Service\WeatherService;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TripServiceTest extends TestCase
{
    private TripService $tripService;
    private MockObject $tripRepository;
    private MockObject $entityManager;
    private MockObject $weatherService;

    protected function setUp(): void
    {
        $this->tripRepository = $this->createMock(TripRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->weatherService = $this->createMock(WeatherService::class);
        
        $this->tripService = new TripService(
            $this->tripRepository,
            $this->entityManager,
            $this->weatherService
        );
    }

    public function testCreateTripWithValidData(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $tripData = [
            'city' => 'Paris',
            'country' => 'France',
            'startDate' => '2025-08-01',
            'endDate' => '2025-08-10'
        ];

        // Mock expectations
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Trip::class));

        $this->entityManager->expects($this->once())->method('flush');
        
        $this->weatherService->expects($this->once())
            ->method('updateWeatherForTrip')
            ->with($this->isInstanceOf(Trip::class));

        // Act
        $result = $this->tripService->createTrip($user, $tripData);

        // Assert
        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals('Paris', $result->getCity());
        $this->assertEquals('France', $result->getCountry());
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals('2025-08-01', $result->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-10', $result->getEndDate()->format('Y-m-d'));
    }

    public function testCreateTripWithInvalidData(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $this->expectException(\InvalidArgumentException::class);

        // Act & Assert
        $this->tripService->createTrip($user, []); // Empty data
    }

    public function testCreateTripWithInvalidDateFormat(): void
    {
        // Arrange
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

        // Act & Assert
        $this->tripService->createTrip($user, $invalidTripData);
    }

    public function testUpdateTripWithValidData(): void
    {
        // Arrange
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

        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $result = $this->tripService->updateTrip($trip, $updateData);

        // Assert
        $this->assertEquals('2025-08-05', $result->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2025-08-15', $result->getEndDate()->format('Y-m-d'));
        $this->assertEquals('Paris', $result->getCity());
        $this->assertEquals('France', $result->getCountry());
    }

    public function testDeleteTripWithValidId(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity('Paris')
             ->setCountry('France')
             ->setStartDate(new \DateTimeImmutable('2025-08-01'))
             ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($trip);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($trip);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->tripService->deleteTrip(1, $user);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteTripNotFound(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trip not found');

        // Act & Assert
        $this->tripService->deleteTrip(999, $user);
    }

    public function testDeleteTripAccessDenied(): void
    {
        // Arrange
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setUsername('user1');

        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setUsername('user2');
        
        $trip = new Trip();
        $trip->setUser($user2); // Trip belongs to user2

        $this->tripRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($trip);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Access denied');

        // Act & Assert - user1 tries to delete user2's trip
        $this->tripService->deleteTrip(1, $user1);
    }

    /**
     * @dataProvider validTripDataProvider
     */
    public function testCreateTripWithVariousValidInputs(array $tripData): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->weatherService->expects($this->once())->method('updateWeatherForTrip');

        // Act
        $result = $this->tripService->createTrip($user, $tripData);

        // Assert
        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals($tripData['city'], $result->getCity());
        $this->assertEquals($tripData['country'], $result->getCountry());
    }

    /**
     * @dataProvider invalidTripDataProvider
     */
    public function testCreateTripWithVariousInvalidInputs(array $tripData, string $expectedException): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->expectException($expectedException);

        // Act & Assert
        $this->tripService->createTrip($user, $tripData);
    }

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
