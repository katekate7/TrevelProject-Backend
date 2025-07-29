<?php

/**
 * @fileoverview TripItemController - API Controller for Trip-Specific Item Management
 * 
 * This controller manages the relationship between trips and items, providing
 * trip-specific checklist functionality. It allows users to view all available
 * items for a trip and toggle their checked/packed status.
 * 
 * Features:
 * - Trip-specific item listing with checked status
 * - Item importance prioritization (important items displayed first)
 * - Toggle functionality for checking/unchecking items
 * - User ownership verification for trips
 * - Automatic TripItem relationship creation
 * 
 * API Endpoints:
 * - GET /api/trips/{tripId}/items - List all items with checked status for trip
 * - POST /api/trips/{tripId}/items/{itemId} - Toggle item checked status for trip
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Item;
use App\Entity\TripItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * TripItemController - Manages trip-specific item checklists and status
 * 
 * Provides functionality for managing item checklists on a per-trip basis.
 * Handles the relationship between trips and items, allowing users to track
 * which items they've packed or prepared for specific trips.
 */
#[Route('/api/trips/{tripId}/items', name: 'api_trip_items_')]
class TripItemController extends AbstractController
{
    /**
     * Constructor - Injects entity manager for database operations
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     */
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * List all items with checked status for a trip
     * 
     * Returns all available items with their checked status for the specified trip.
     * Items are ordered by importance (important items first) then alphabetically.
     * Only trip owners can access their trip items.
     * 
     * @Route("", name="list", methods={"GET"})
     * @param int $tripId Trip ID to get items for
     * @return JsonResponse Array of items with id, name, importance, and checked status
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $tripId): JsonResponse
    {
        // Verify user authentication and trip ownership
        $user = $this->getUser();
        $trip = $this->em->getRepository(Trip::class)->find($tripId);
        if (!$trip || $trip->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Get all items ordered by importance (important first) then name
        $all = $this->em->getRepository(Item::class)
            ->createQueryBuilder('i')
            ->orderBy('i.important', 'DESC')     // Important items first
            ->addOrderBy('i.name', 'ASC')        // Then alphabetical by name
            ->getQuery()
            ->getResult();

        // Get existing TripItem relationships for this trip
        $tis = $this->em->getRepository(TripItem::class)
            ->findBy(['trip' => $trip]);

        // Create map of item ID to checked status
        $map = [];
        foreach ($tis as $ti) {
            $map[$ti->getItem()->getId()] = $ti->isChecked();
        }

        // Build response array with all items and their checked status
        $out = [];
        foreach ($all as $i) {
            $out[] = [
                'id' => $i->getId(),
                'name' => $i->getName(),
                'important' => $i->isImportant(),           // High-priority item flag
                'isChecked' => $map[$i->getId()] ?? false,  // Checked status for this trip
            ];
        }

        return $this->json($out);
    }

    /**
     * Toggle item checked status for a trip
     * 
     * Toggles the checked/packed status of an item for a specific trip.
     * Creates TripItem relationship if it doesn't exist, otherwise toggles existing status.
     * Only trip owners can modify their trip items.
     * 
     * @Route("/{itemId}", name="toggle", methods={"POST"})
     * @param int $tripId Trip ID for the item
     * @param int $itemId Item ID to toggle
     * @return JsonResponse Current checked status or error if not found/unauthorized
     */
    #[Route('/{itemId}', name: 'toggle', methods: ['POST'])]
    public function toggle(int $tripId, int $itemId): JsonResponse
    {
        // Verify user authentication and find entities
        $user = $this->getUser();
        $trip = $this->em->getRepository(Trip::class)->find($tripId);
        $item = $this->em->getRepository(Item::class)->find($itemId);

        // Verify trip ownership and entity existence
        if (!$trip || $trip->getUser() !== $user || !$item) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Find existing TripItem relationship
        $repo = $this->em->getRepository(TripItem::class);
        $ti = $repo->findOneBy(['trip' => $trip, 'item' => $item]);

        if (!$ti) {
            // Create new TripItem relationship, mark as checked
            $ti = (new TripItem())
                ->setTrip($trip)
                ->setItem($item)
                ->setChecked(true)
                ->setAddedBy($this->getUser());  // Track who added the item

            $this->em->persist($ti);
        } else {
            // Toggle existing checked status
            $ti->setChecked(!$ti->isChecked());
        }

        // Save changes to database
        $this->em->flush();
        
        // Return current checked status
        return $this->json(['taken' => $ti->isChecked()]);
    }
}
