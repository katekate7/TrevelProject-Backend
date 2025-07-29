<?php

/**
 * @fileoverview ItemRequestController - API Controller for User Item Requests
 * 
 * This controller manages user requests for new travel items to be added to the
 * system catalog. It provides a workflow for users to suggest items and for
 * administrators to review and approve/reject these suggestions.
 * 
 * Features:
 * - User item request submission
 * - Admin review and approval workflow
 * - Request status tracking (pending, approved, rejected)
 * - Automatic item creation upon approval
 * - Admin-only request management
 * 
 * API Endpoints:
 * - POST /api/item-requests - Submit new item request (authenticated users)
 * - GET /api/item-requests - List item requests with optional status filter (admin only)
 * - PATCH /api/item-requests/{id} - Review request (approve/reject) (admin only)
 * - DELETE /api/item-requests/{id} - Delete request (admin only)
 * 
 * Workflow:
 * 1. User submits item request
 * 2. Admin reviews request
 * 3. Admin approves (creates item) or rejects
 * 4. Request status updated accordingly
 * 
 * @package App\Controller\Api
 * @author Travel Planner Development Team
 * @version 1.0.0
 */

namespace App\Controller\Api;

use App\Entity\Item;
use App\Entity\ItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ItemRequestController - Manages user requests for new travel items
 * 
 * Provides a workflow system for users to request new items be added to the
 * travel catalog, with admin review and approval processes. Facilitates
 * community-driven catalog expansion while maintaining quality control.
 */
#[Route('/api/item-requests', name: 'api_item_requests_')]
class ItemRequestController extends AbstractController
{
    /**
     * Constructor - Injects entity manager for database operations
     * 
     * @param EntityManagerInterface $em Entity manager for database operations
     */
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Create new item request (authenticated users)
     * 
     * Allows authenticated users to submit requests for new items to be added
     * to the travel catalog. Request starts with 'pending' status for admin review.
     * 
     * @Route("", name="create", methods={"POST"})
     * @param Request $request HTTP request containing item name
     * @return JsonResponse Created request data or validation errors
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) return $this->json([], 401);

        // Parse and validate request data
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        // Create new item request with pending status
        $ir = (new ItemRequest())
            ->setName($data['name'])
            ->setUser($user);                 // Status defaults to 'pending'

        // Save request to database
        $this->em->persist($ir);
        $this->em->flush();

        // Return created request data
        return $this->json([
            'message' => 'Request submitted',
            'request' => [
                'id' => $ir->getId(),
                'name' => $ir->getName(),
                'status' => $ir->getStatus(),
            ],
        ], 201);
    }

    /**
     * List item requests with optional status filter (admin only)
     * 
     * Returns list of item requests for admin review. Supports optional status
     * filtering via query parameter to show only pending, approved, or rejected requests.
     * 
     * @Route("", name="list", methods={"GET"})
     * @param Request $request HTTP request with optional status query parameter
     * @return JsonResponse Array of item requests with user and status information
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Ensure only admins can view requests
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get optional status filter from query parameters
        $status = $request->query->get('status');               // ?status=pending
        $criteria = $status ? ['status' => $status] : [];

        // Fetch requests with optional filtering, ordered by ID
        $reqs = $this->em->getRepository(ItemRequest::class)
                         ->findBy($criteria, ['id' => 'ASC']);

        // Transform requests to JSON-friendly format
        return $this->json(array_map(fn(ItemRequest $r) => [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'requestedBy' => $r->getUser()?->getEmail(),  // Requester email
            'status' => $r->getStatus(),                  // pending/approved/rejected
        ], $reqs));
    }

    /**
     * Review item request - approve or reject (admin only)
     * 
     * Allows admins to approve or reject pending item requests. Approval automatically
     * creates the item in the catalog and updates request status. Only pending requests
     * can be reviewed.
     * 
     * @Route("/{id}", name="review", methods={"PATCH"})
     * @param int $id Item request ID to review
     * @param Request $request HTTP request containing action (approve/reject)
     * @return JsonResponse Review result or validation errors
     */
    #[Route('/{id}', name: 'review', methods: ['PATCH'])]
    public function review(int $id, Request $request): JsonResponse
    {
        // Ensure only admins can review requests
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var ItemRequest|null $ir */
        $ir = $this->em->find(ItemRequest::class, $id);
        if (!$ir) return $this->json(['error' => 'Not found'], 404);
        
        // Only pending requests can be reviewed
        if ($ir->getStatus() !== 'pending')
            return $this->json(['error' => 'Already reviewed'], 409);

        // Parse and validate review action
        $data = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null;
        if (!\in_array($action, ['approve', 'reject'], true)) {
            return $this->json(['error' => 'action must be approve|reject'], 400);
        }

        if ($action === 'approve') {
            // Create new item in catalog upon approval
            $item = (new Item())
                ->setName($ir->getName());
            $this->em->persist($item);
            $ir->setStatus('approved');
        } else {
            // Reject request without creating item
            $ir->setStatus('rejected');
        }

        // Save review decision
        $this->em->flush();
        return $this->json(['message' => "Request $action"]);
    }

    /**
     * Delete item request (admin only)
     * 
     * Permanently removes an item request from the system. This action is
     * irreversible and should be used with caution.
     * 
     * @Route("/{id}", name="delete", methods={"DELETE"})
     * @param int $id Item request ID to delete
     * @return JsonResponse Success message or error if not found
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Ensure only admins can delete requests
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Find and remove request
        $ir = $this->em->find(ItemRequest::class, $id);
        if (!$ir) return $this->json(['error' => 'Not found'], 404);

        $this->em->remove($ir);
        $this->em->flush();
        
        return $this->json(['message' => 'Request deleted']);
    }
}
