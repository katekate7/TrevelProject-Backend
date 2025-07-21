<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;

class TripService
{
    public function __construct(
        private TripRepository $tripRepository,
        private EntityManagerInterface $em,
        private WeatherService $weatherService
    ) {}

    public function createTrip(User $user, array $data): Trip
    {
        $this->validateTripData($data);
        
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity($data['city'])
             ->setCountry($data['country'])
             ->setStartDate(new \DateTimeImmutable($data['startDate']))
             ->setEndDate(new \DateTimeImmutable($data['endDate']));

        $this->em->persist($trip);
        $this->em->flush();

        // Initialize weather data
        $this->weatherService->updateWeatherForTrip($trip);

        return $trip;
    }

    public function updateTrip(Trip $trip, array $data): Trip
    {
        if (isset($data['startDate'])) {
            $trip->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        
        if (isset($data['endDate'])) {
            $trip->setEndDate(new \DateTimeImmutable($data['endDate']));
        }

        $this->em->flush();
        return $trip;
    }

    public function deleteTrip(int $tripId, User $user): bool
    {
        $trip = $this->tripRepository->find($tripId);
        
        if (!$trip) {
            throw new \InvalidArgumentException('Trip not found');
        }
        
        if ($trip->getUser() !== $user) {
            throw new \InvalidArgumentException('Access denied');
        }
        
        $this->em->remove($trip);
        $this->em->flush();
        
        return true;
    }

    private function validateTripData(array $data): void
    {
        $requiredFields = ['city', 'country', 'startDate', 'endDate'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }
        
        // Validate date format
        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $data['startDate']);
        $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $data['endDate']);
        
        if (!$startDate) {
            throw new \Exception('Invalid start date format');
        }
        
        if (!$endDate) {
            throw new \Exception('Invalid end date format');
        }
        
        if ($endDate < $startDate) {
            throw new \Exception('End date cannot be before start date');
        }
    }
}
