<?php
/**
 * @fileoverview TripRouteController - API Controller for Trip Route Management
 * 
 * This controller manages route data for trips, including saving and retrieving
 * route information with geolocation data and distance calculations. It handles
 * the trip navigation and route planning functionality.
 * 
 * Features:
 * - Route data storage and retrieval
 * - Distance tracking for routes
 * - Geolocation data management
 * - Route history with timestamps
 * - Trip-specific route association
 * 
 * API Endpoints:
 * - GET /api/trips/{trip}/route - Get latest route data for trip
 * - POST /api/trips/{trip}/route - Save new route data for trip
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */
// src/Controller/Api/TripRouteController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Trajet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * TripRouteController - Manages route data and navigation for trips
 * 
 * Handles the storage and retrieval of route information including geolocation
 * data, distance calculations, and route history for travel planning and navigation.
 */
#[Route('/api/trips/{trip<\d+>}/route', name:'api_trip_route_')]
class TripRouteController extends AbstractController
{
    /**
     * Constructor - Injects entity manager for database operations
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     */
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Get latest route data for trip
     * 
     * Retrieves the most recent route information for a specific trip,
     * including geolocation data, distance, and creation timestamp.
     * Returns 204 No Content if no route exists.
     * 
     * @Route("", name="get", methods={"GET"})
     * @param Trip $trip Trip entity (auto-resolved by Symfony)
     * @return JsonResponse Route data with geolocation, distance, and timestamp or empty response
     */
    #[Route('', name:'get', methods:['GET'])]
    public function get(Trip $trip): JsonResponse
    {
        // Get the latest route (Trajet) for this trip
        $t = $trip->getLastTrajet();
        if (!$t) {
            // No route data found, return empty response with 204 status
            return $this->json([], 204);
        }
        
        // Return route data with geolocation, distance, and timestamp
        return $this->json([
            'routeData' => $t->getRouteData(),  // Geolocation coordinates and waypoints
            'distance' => $t->getDistance(),    // Total route distance
            'createdAt' => $t->getCreatedAt()->format('c'),  // ISO 8601 timestamp
        ]);
    }

    /**
     * Save new route data for trip
     * 
     * Creates and stores a new route record for the trip with geolocation data
     * and distance information. Each save creates a new route entry for history tracking.
     * 
     * @Route("", name="save", methods={"POST"})
     * @param Trip $trip Trip entity (auto-resolved by Symfony)
     * @param Request $req HTTP request containing route data and distance
     * @return JsonResponse Success confirmation with saved route ID
     */
    #[Route('', name:'save', methods:['POST'])]
    public function save(Trip $trip, Request $req): JsonResponse
    {
        // Parse request data
        $b = json_decode($req->getContent(), true);
        $geo = $b['routeData'] ?? [];  // Geolocation coordinates array
        $dist = isset($b['distance']) ? (float)$b['distance'] : null;  // Route distance in km

        // Create new route (Trajet) entity
        $t = (new Trajet())
            ->setTrip($trip)
            ->setRouteData($geo)    // Store geolocation waypoints and coordinates
            ->setDistance($dist);   // Store calculated route distance

        // Save route to database
        $this->em->persist($t);
        $this->em->flush();

        // Return success confirmation with route ID
        return $this->json(['saved' => true, 'id' => $t->getId()]);
    }
}
