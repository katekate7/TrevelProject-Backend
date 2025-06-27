<?php
// src/Controller/Api/ItemRequestController.php

namespace App\Controller\Api;

use App\Entity\Item;
use App\Entity\ItemRequest;
use App\Entity\TripItem;          // ← якщо потрібно для інших дій
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/item-requests', name: 'api_item_requests_')]
class ItemRequestController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /* ──────────────────────────────────────────────
     * USER: POST /api/item-requests  → створити запит
     * ────────────────────────────────────────────── */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $ir = (new ItemRequest())
            ->setName($data['name'])
            ->setDescription($data['description'] ?? null)
            ->setStatus('pending')
            ->setUser($user);

        $this->em->persist($ir);
        $this->em->flush();

        return $this->json([
            'message' => 'Request submitted',
            'request' => [
                'id'        => $ir->getId(),
                'name'      => $ir->getName(),
                'status'    => $ir->getStatus(),
                'createdAt' => $ir->getCreatedAt()->format('Y-m-d H:i'),
            ],
        ], 201);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: GET /api/item-requests[?status=pending]
     * ────────────────────────────────────────────── */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');          // pending|approved|rejected|null
        $criteria = $status ? ['status' => $status] : [];

        $requests = $this->em->getRepository(ItemRequest::class)
                             ->findBy($criteria, ['id' => 'ASC']);

        $data = array_map(fn(ItemRequest $r) => [
            'id'          => $r->getId(),
            'name'        => $r->getName(),
            'description' => $r->getDescription(),
            'requestedBy' => $r->getUser()?->getEmail(),
            'status'      => $r->getStatus(),
            'createdAt'   => $r->getCreatedAt()->format('Y-m-d H:i'),
        ], $requests);

        return $this->json($data);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: PATCH /api/item-requests/{id}
     *        { action: approve|reject, importanceLevel? }
     * ────────────────────────────────────────────── */
    #[Route('/{id}', name: 'review', methods: ['PATCH'])]
    public function review(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var ItemRequest|null $reqEntity */
        $reqEntity = $this->em->find(ItemRequest::class, $id);
        if (!$reqEntity) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($reqEntity->getStatus() !== 'pending') {
            return $this->json(['error' => 'Already reviewed'], 409);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null;

        if (!\in_array($action, ['approve', 'reject'], true)) {
            return $this->json(['error' => 'action must be approve|reject'], 400);
        }

        /* ---------- approve ---------- */
        if ($action === 'approve') {
            // створюємо глобальний Item
            $item = (new Item())
                ->setName($reqEntity->getName())
                ->setDescription($reqEntity->getDescription())
                ->setImportanceLevel($data['importanceLevel'] ?? 'optional');

            $this->em->persist($item);
            $reqEntity->setStatus('approved');
        } else {
            /* --------- reject ---------- */
            $reqEntity->setStatus('rejected');
        }

        $this->em->flush();

        return $this->json(['message' => 'Request ' . $action]);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: DELETE /api/item-requests/{id}
     *        → повністю прибрати запит
     * ────────────────────────────────────────────── */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $reqEntity = $this->em->find(ItemRequest::class, $id);
        if (!$reqEntity) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->em->remove($reqEntity);
        $this->em->flush();

        return $this->json(['message' => 'Request deleted']);
    }
}
