<?php

/**
 * @fileoverview DestinationController - API Controller for Destination Management
 * 
 * This controller manages travel destinations including listing, viewing details,
 * and allowing users to add new destinations. It provides a destination catalog
 * for travel planning and inspiration.
 * 
 * Features:
 * - Destination listing and browsing
 * - Individual destination details
 * - User-contributed destination additions
 * - Destination information with name, country, and description
 * - Public access for browsing, authenticated access for contributions
 * 
 * API Endpoints:
 * - GET /api/destinations/ - List all destinations
 * - GET /api/destinations/{id} - Get specific destination details
 * - POST /api/destinations/add - Add new destination (authenticated users only)
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */

namespace App\Controller\Api;

use App\Entity\Destination;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * DestinationController - Manages travel destination catalog
 * 
 * Provides functionality for browsing and contributing to a shared catalog
 * of travel destinations. Allows public access for viewing and authenticated
 * user access for adding new destinations to the community database.
 */
#[Route('/api/destinations')]
class DestinationController extends AbstractController
{
    /**
     * Get all destinations
     * 
     * Returns a complete list of all destinations in the database.
     * Public endpoint that doesn't require authentication for browsing.
     * 
     * @Route("/", name="get_destinations", methods={"GET"})
     * @param DestinationRepository $destinationRepository Repository for destination queries
     * @return JsonResponse Array of all destination objects
     */
    #[Route('/', name: 'get_destinations', methods: ['GET'])]
    public function getDestinations(DestinationRepository $destinationRepository): JsonResponse
    {
        // Fetch and return all destinations from database
        return $this->json($destinationRepository->findAll());
    }

    /**
     * Get specific destination details
     * 
     * Returns detailed information for a single destination including
     * name, country, description, and any other associated data.
     * 
     * @Route("/{id}", name="get_destination", methods={"GET"})
     * @param Destination $destination Destination entity (auto-resolved by Symfony)
     * @return JsonResponse Destination object with full details
     */
    #[Route('/{id}', name: 'get_destination', methods: ['GET'])]
    public function getDestination(Destination $destination): JsonResponse
    {
        // Return complete destination data
        return $this->json($destination);
    }

    /**
     * Add new destination (authenticated users only)
     * 
     * Allows authenticated users to contribute new destinations to the catalog.
     * Validates required fields and creates new destination entry.
     * 
     * @Route("/add", name="add_destination", methods={"POST"})
     * @IsGranted("ROLE_USER")
     * @param Request $request HTTP request containing destination data
     * @param EntityManagerInterface $entityManager Entity manager for database operations
     * @return JsonResponse Success message or validation errors
     */
    #[Route('/add', name: 'add_destination', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]  // Access only for authenticated users (not just admins)
    public function addDestination(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Parse and validate request data
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['country'], $data['description'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        // Create new destination entity
        $destination = new Destination();
        $destination->setName($data['name']);
        $destination->setCountry($data['country']);
        $destination->setDescription($data['description']);

        // Save destination to database
        $entityManager->persist($destination);
        $entityManager->flush();

        return $this->json(['message' => 'Destination added successfully']);
    }
}
