<?php
/**
 * Trip Service
 * 
 * This service handles all business logic related to trip management,
 * including creation, updates, deletion, and validation of trip data.
 * It serves as an intermediary between controllers and the data layer.
 * 
 * @package App\Service
 * @author Travel Project Team
 */

namespace App\Service;

use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Core service for trip management functionality
 * 
 * This class centralizes all trip-related operations, ensuring consistent
 * business rules and validation across the application. It manages the
 * trip lifecycle and coordinates with other services like WeatherService.
 */
class TripService
{
    /**
     * Constructor with dependency injection
     * 
     * @param TripRepository $tripRepository Repository for trip data operations
     * @param EntityManagerInterface $em Doctrine entity manager for persistence
     * @param WeatherService $weatherService Service for weather data management
     */
    public function __construct(
        private TripRepository $tripRepository,
        private EntityManagerInterface $em,
        private WeatherService $weatherService
    ) {}

    /**
     * Create a new trip for a user
     * 
     * Creates a new trip entity with the provided data, validates it,
     * persists it to the database, and initializes associated weather data.
     * 
     * @param User $user The user who owns this trip
     * @param array $data Trip data including city, country, start and end dates
     * @return Trip The newly created and persisted trip entity
     * @throws \InvalidArgumentException If trip data is invalid
     * @throws \Exception If date formats are invalid
     */
    public function createTrip(User $user, array $data): Trip
    {
        // Validate all required fields and date formats
        $this->validateTripData($data);
        
        // Create and populate the trip entity
        $trip = new Trip();
        $trip->setUser($user)
             ->setCity($data['city'])
             ->setCountry($data['country'])
             ->setStartDate(new \DateTimeImmutable($data['startDate']))
             ->setEndDate(new \DateTimeImmutable($data['endDate']));

        // Save to database
        $this->em->persist($trip);
        $this->em->flush();

        // Initialize weather data for the trip's location and dates
        $this->weatherService->updateWeatherForTrip($trip);

        return $trip;
    }

    /**
     * Update an existing trip
     * 
     * Updates the provided trip entity with new data.
     * Currently supports updating dates only.
     * 
     * @param Trip $trip The trip entity to update
     * @param array $data New data for the trip
     * @return Trip The updated trip entity
     */
    public function updateTrip(Trip $trip, array $data): Trip
    {
        // Update start date if provided
        if (isset($data['startDate'])) {
            $trip->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        
        // Update end date if provided
        if (isset($data['endDate'])) {
            $trip->setEndDate(new \DateTimeImmutable($data['endDate']));
        }

        // Save changes to database
        $this->em->flush();
        return $trip;
    }

    /**
     * Delete a trip
     * 
     * Removes a trip from the database after verifying that it exists
     * and belongs to the specified user (authorization check).
     * 
     * @param int $tripId ID of the trip to delete
     * @param User $user User attempting to delete the trip
     * @return bool True if the trip was successfully deleted
     * @throws \InvalidArgumentException If trip doesn't exist or user doesn't own it
     */
    public function deleteTrip(int $tripId, User $user): bool
    {
        // Find the trip by ID
        $trip = $this->tripRepository->find($tripId);
        
        // Check if trip exists
        if (!$trip) {
            throw new \InvalidArgumentException('Trip not found');
        }
        
        // Authorization check - ensure user owns this trip
        if ($trip->getUser() !== $user) {
            throw new \InvalidArgumentException('Access denied');
        }
        
        // Delete from database
        $this->em->remove($trip);
        $this->em->flush();
        
        return true;
    }

    /**
     * Validate trip data
     * 
     * Internal method to validate trip data before creation or update.
     * Checks for required fields and ensures dates are valid and logical.
     * 
     * @param array $data Trip data to validate
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     * @throws \Exception If dates are invalid or end date is before start date
     */
    private function validateTripData(array $data): void
    {
        // Check all required fields are present and not empty
        $requiredFields = ['city', 'country', 'startDate', 'endDate'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }
        
        // Validate date format (YYYY-MM-DD)
        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $data['startDate']);
        $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $data['endDate']);
        
        // Check if start date is valid
        if (!$startDate) {
            throw new \Exception('Invalid start date format');
        }
        
        // Check if end date is valid
        if (!$endDate) {
            throw new \Exception('Invalid end date format');
        }
        
        // Check if end date is after start date
        if ($endDate < $startDate) {
            throw new \Exception('End date cannot be before start date');
        }
    }
}
