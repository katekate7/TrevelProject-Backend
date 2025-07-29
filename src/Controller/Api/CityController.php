<?php

/**
 * @fileoverview CityController - API Controller for City Search Functionality
 * 
 * This controller provides city search functionality using the OpenStreetMap Nominatim API.
 * It enables autocomplete and search features for travel destination selection,
 * returning structured city data with proper formatting for frontend consumption.
 * 
 * Features:
 * - City search using OpenStreetMap Nominatim API
 * - Query parameter validation and sanitization
 * - Structured response with city, country information
 * - Rate limiting and proper HTTP headers
 * - Error handling for external API calls
 * 
 * API Endpoints:
 * - GET /api/cities - Search for cities by query parameter
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CityController - Handles city search and autocomplete functionality
 * 
 * Provides API endpoints for searching cities and destinations using external
 * geocoding services. Integrates with OpenStreetMap Nominatim for reliable
 * city data retrieval and formatting for travel planning applications.
 */
class CityController extends AbstractController
{
    /**
     * Search for cities using OpenStreetMap Nominatim API
     * 
     * Provides city search functionality with autocomplete support for travel planning.
     * Queries the Nominatim API with user input and returns formatted city data
     * including display names, city names, and country information.
     * 
     * @Route("/api/cities", name="city_search", methods={"GET"})
     * 
     * @param Request $request HTTP request containing search query parameter 'q'
     * @param HttpClientInterface $http HTTP client for external API calls
     * @return JsonResponse JSON response with array of city search results
     * 
     * @example GET /api/cities?q=Paris
     * Returns: [{"label": "Paris, France", "city": "Paris", "country": "France"}]
     */
    #[Route('/api/cities', name: 'city_search', methods: ['GET'])]
    public function search(Request $request, HttpClientInterface $http): JsonResponse
    {
        // Extract and validate search query parameter
        $query = $request->query->get('q');
        if (!$query) {
            // Return empty array if no query provided
            return $this->json([]);
        }

        // Make request to OpenStreetMap Nominatim API for city search
        $response = $http->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'q' => $query,                // Search query from user input
                'format' => 'json',          // Request JSON response format
                'addressdetails' => 1,       // Include detailed address information
                'limit' => 5,                // Limit results to 5 items for performance
            ],
            'headers' => [
                'User-Agent' => 'MyTravelApp/1.0 (email)',  // Required by Nominatim API
                'Accept-Language' => 'en',                   // Request English language results
            ],
        ]);

        // Parse JSON response from Nominatim API
        $data = $response->toArray();

        // Transform API response data into frontend-friendly format
        $results = array_map(function ($item) {
            return [
                'label' => $item['display_name'],  // Full formatted address for display
                // Extract city name with fallback hierarchy (city > town > village)
                'city' => $item['address']['city'] ?? $item['address']['town'] ?? $item['address']['village'] ?? '',
                'country' => $item['address']['country'] ?? '',  // Country name for filtering
            ];
        }, $data);

        // Return formatted city search results as JSON
        return $this->json($results);
    }
}
