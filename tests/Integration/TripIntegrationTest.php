<?php
namespace App\Tests\Integration;

use App\Entity\Trip;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class TripIntegrationTest extends TestCase
{
    public function testTripSimpleProperties(): void
    {
        // Arrange: create a fake User
        $user = (new User())
            ->setEmail('simple@test.dev')
            ->setUsername('simpleuser')
            ->setPassword('hashedpass')
            ->setRole('user');

        // Arrange: create a Trip without touching Doctrine
        $trip = (new Trip())
            ->setUser($user)
            ->setCity('Test City')
            ->setCountry('Testland')
            ->setStartDate(new \DateTimeImmutable('2025-08-01'))
            ->setEndDate(new \DateTimeImmutable('2025-08-10'));

        // Assert: check values directly
        $this->assertSame('Test City', $trip->getCity());
        $this->assertSame('Testland', $trip->getCountry());
        $this->assertSame($user, $trip->getUser());
        $this->assertSame('2025-08-01', $trip->getStartDate()->format('Y-m-d'));
        $this->assertSame('2025-08-10', $trip->getEndDate()->format('Y-m-d'));
    }
}
