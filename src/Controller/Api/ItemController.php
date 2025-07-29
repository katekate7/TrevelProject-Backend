<?php

/**
 * @fileoverview ItemController - API Controller for Travel Item Management
 * 
 * This controller manages travel items that can be added to trip checklists.
 * It provides CRUD operations for items and handles item checklist functionality
 * for individual trips, allowing users to track packed/unpacked items.
 * 
 * Features:
 * - Item CRUD operations (admin only for create/update/delete)
 * - Item listing for all users
 * - Trip-specific item checklist management
 * - Toggle functionality for marking items as taken/not taken
 * - Important item flagging for priority items
 * 
 * API Endpoints:
 * - GET /api/items - List all available items
 * - POST /api/items - Create new item (admin only)
 * - PATCH /api/items/{id} - Update item (admin only)
 * - DELETE /api/items/{id} - Delete item (admin only)
 * - POST /api/items/{itemId}/toggle/{tripId} - Toggle item status for trip
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */

namespace App\Controller\Api;

use App\Entity\Item;
use App\Entity\Trip;
use App\Entity\TripItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ItemController - Manages travel items and trip-specific checklists
 * 
 * Handles travel item management including global item catalog and trip-specific
 * checklist functionality. Allows users to track which items they've packed
 * for each trip, with admin controls for item catalog management.
 */
#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    /**
     * Constructor - Injects entity manager for database operations
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     */
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * List all available travel items
     * 
     * Returns a complete list of all travel items available for trip checklists.
     * Includes item ID, name, and importance flag for frontend display.
     * 
     * @Route("", name="list", methods={"GET"})
     * @return JsonResponse Array of item objects with id, name, and importance flag
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Fetch all items from database
        $items = $this->em->getRepository(Item::class)
            ->createQueryBuilder('i')
            ->getQuery()
            ->getResult();

        // Transform items to JSON-friendly format
        $data = array_map(fn(Item $i) => [
            'id' => $i->getId(),
            'name' => $i->getName(),
            'important' => $i->isImportant(),  // Flag for high-priority items
        ], $items);

        return $this->json($data);
    }

    /**
     * Create new travel item (admin only)
     * 
     * Creates a new item in the global travel items catalog. Only administrators
     * can add new items to maintain catalog quality and prevent duplicates.
     * 
     * @Route("", name="create", methods={"POST"})
     * @param Request $request HTTP request containing item name and importance flag
     * @return JsonResponse Created item data or validation errors
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Ensure only admins can create items
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Parse and validate request data
        $d = json_decode($request->getContent(), true);

        if (empty($d['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        // Create new item with provided data
        $item = (new Item())
            ->setName($d['name'])
            ->setImportant(!empty($d['important']));

        // Set importance flag if provided
        if (isset($d['important'])) $item->setImportant($d['important']);

        // Save item to database
        $this->em->persist($item);
        $this->em->flush();

        // Return created item data
        return $this->json([
            'message' => 'Item created',
            'item' => [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'important' => $item->isImportant(),
            ],
        ], 201);
    }

    /**
     * Update existing travel item (admin only)
     * 
     * Updates item name and/or importance flag. Supports partial updates
     * where only provided fields are modified.
     * 
     * @Route("/{id}", name="update", methods={"PATCH"})
     * @param Item $item Item entity to update (auto-resolved by Symfony)
     * @param Request $request HTTP request containing updated fields
     * @return JsonResponse Success message or validation errors
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(Item $item, Request $request): JsonResponse
    {
        // Ensure only admins can update items
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Parse request data
        $d = json_decode($request->getContent(), true);

        // Update fields if provided
        if (isset($d['name'])) $item->setName($d['name']);
        if (isset($d['important'])) $item->setImportant($d['important']);

        // Save changes to database
        $this->em->flush();

        return $this->json(['message' => 'Item updated']);
    }

    /**
     * Delete travel item (admin only)
     * 
     * Permanently removes an item from the catalog. This will affect all
     * existing trip checklists that reference this item.
     * 
     * @Route("/{id}", name="delete", methods={"DELETE"})
     * @param Item $item Item entity to delete (auto-resolved by Symfony)
     * @return JsonResponse Success message
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Item $item): JsonResponse
    {
        // Ensure only admins can delete items
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Remove item from database
        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'Item deleted']);
    }

    /**
     * Toggle item status for specific trip (taken/not taken)
     * 
     * Allows users to mark items as packed or unpacked for their trips.
     * Creates TripItem relationship if it doesn't exist, or toggles existing status.
     * 
     * @Route("/{itemId}/toggle/{tripId}", name="toggle", methods={"POST"})
     * @param int $itemId ID of the item to toggle
     * @param int $tripId ID of the trip for item status
     * @return JsonResponse Current taken status or error if not found/unauthorized
     */
    #[Route('/{itemId}/toggle/{tripId}', name: 'toggle', methods: ['POST'])]
    public function toggleTaken(int $itemId, int $tripId): JsonResponse
    {
        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json([], 401);
        }

        // Find item and trip entities
        $item = $this->em->find(Item::class, $itemId);
        $trip = $this->em->find(Trip::class, $tripId);

        // Verify entities exist and user owns the trip
        if (!$item || !$trip || $trip->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Find existing TripItem relationship
        $repo = $this->em->getRepository(TripItem::class);
        $ti = $repo->findOneBy(['trip' => $trip, 'item' => $item]);

        if (!$ti) {
            // Create new TripItem relationship, mark as taken
            $ti = (new TripItem())
                ->setTrip($trip)
                ->setItem($item)
                ->setTaken(true);
            $this->em->persist($ti);
        } else {
            // Toggle existing taken status
            $ti->setTaken(!$ti->isTaken());
        }

        // Save changes to database
        $this->em->flush();

        // Return current taken status
        return $this->json(['taken' => $ti->isTaken()]);
    }
}