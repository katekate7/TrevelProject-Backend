<?php
/**
 * @fileoverview TripPlaceController - API Controller for Trip Places and Waypoints
 * 
 * This controller manages places and waypoints associated with specific trips.
 * It handles the storage and retrieval of geographical points of interest,
 * waypoints, and stops that are part of a trip's itinerary.
 * 
 * Features:
 * - Trip-specific place management
 * - Waypoint storage with coordinates (latitude/longitude)
 * - Place listing for trip itineraries
 * - Bulk waypoint updates and replacements
 * - Geographic coordinate tracking
 * 
 * API Endpoints:
 * - GET /api/trips/{trip}/places - List all places for a trip
 * - POST /api/trips/{trip}/places - Save/update waypoints for a trip
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */
// src/Controller/Api/TripPlaceController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * TripPlaceController - Manages places and waypoints for trip itineraries
 * 
 * Handles the geographic points of interest and waypoints that make up a trip's
 * itinerary. Provides functionality to list, save, and update places with
 * coordinate information for mapping and navigation purposes.
 */
#[Route('/api/trips/{trip<\d+>}/places', name:'api_trip_places_')]
class TripPlaceController extends AbstractController
{
    /**
     * Constructor - Injects entity manager for database operations
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     */
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * List all places for a trip
     * 
     * Returns all places and waypoints associated with the specified trip,
     * including coordinates and titles for mapping and itinerary display.
     * 
     * @Route("", name="list", methods={"GET"})
     * @param Trip $trip Trip entity (auto-resolved by Symfony)
     * @return JsonResponse Array of place objects with id, title, latitude, longitude
     */
    #[Route('', name:'list', methods:['GET'])]
    public function list(Trip $trip): JsonResponse
    {
        // Transform trip places to JSON-friendly format with coordinates
        $out = array_map(fn(Place $p) => [
            'id' => $p->getId(),
            'title' => $p->getName(),  // Place name/title
            'lat' => $p->getLat(),     // Latitude coordinate
            'lng' => $p->getLng(),     // Longitude coordinate
        ], $trip->getPlaces()->toArray());

        return $this->json($out);
    }

    /**
     * Save/update waypoints for a trip
     * 
     * Replaces all existing places for a trip with new waypoints data.
     * Performs a complete refresh of the trip's places collection.
     * 
     * @Route("", name="save", methods={"POST"})
     * @param Trip $trip Trip entity (auto-resolved by Symfony)
     * @param Request $req HTTP request containing waypoints array
     * @return JsonResponse Success confirmation
     */
    #[Route('', name:'save', methods:['POST'])]
    public function save(Trip $trip, Request $req): JsonResponse
    {
        // Parse request data for waypoints
        $body = json_decode($req->getContent(), true);
        $wps = $body['waypoints'] ?? [];

        // Clear existing places for this trip
        foreach($trip->getPlaces() as $old) {
            $this->em->remove($old);
        }
        $trip->getPlaces()->clear();

        // Add new places from waypoints data
        foreach($wps as $wp) {
            $p = (new Place())
                ->setTrip($trip)
                ->setName($wp['title'])           // Place name/title
                ->setLat((float)$wp['lat'])       // Latitude coordinate
                ->setLng((float)$wp['lng']);      // Longitude coordinate
            
            $this->em->persist($p);
        }

        // Save all changes to database
        $this->em->flush();
        return $this->json(['saved' => true]);
    }
}
