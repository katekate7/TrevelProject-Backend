<?php

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
 * @Route("/api/items", name="api_items_")
 */
class ItemController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /* ──────────────────────────────────────────────
     * PUBLIC: GET /api/items
     * ────────────────────────────────────────────── */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Item::class)
            ->createQueryBuilder('i')
            ->orderBy('i.importanceLevel', 'ASC')   // optional → recommended → critical
            ->getQuery()
            ->getResult();

        $data = array_map(fn(Item $i) => [
            'id'              => $i->getId(),
            'name'            => $i->getName(),
            'description'     => $i->getDescription(),
            'importanceLevel' => $i->getImportanceLevel(),
        ], $items);

        return $this->json($data);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: POST /api/items
     * ────────────────────────────────────────────── */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $d = json_decode($request->getContent(), true);

        if (empty($d['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $item = (new Item())
            ->setName($d['name'])
            ->setDescription($d['description'] ?? null)
            ->setImportanceLevel($d['importanceLevel'] ?? 'optional');

        $this->em->persist($item);
        $this->em->flush();

        return $this->json([
            'message' => 'Item created',
            'item'    => [
                'id'              => $item->getId(),
                'name'            => $item->getName(),
                'importanceLevel' => $item->getImportanceLevel(),
            ],
        ], 201);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: PATCH /api/items/{id}
     * ────────────────────────────────────────────── */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(Item $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $d = json_decode($request->getContent(), true);

        if (isset($d['name']))            $item->setName($d['name']);
        if (array_key_exists('description', $d)) $item->setDescription($d['description']);
        if (isset($d['importanceLevel'])) $item->setImportanceLevel($d['importanceLevel']);

        $this->em->flush();

        return $this->json(['message' => 'Item updated']);
    }

    /* ──────────────────────────────────────────────
     * ADMIN: DELETE /api/items/{id}
     * ────────────────────────────────────────────── */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Item $item): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'Item deleted']);
    }

    /* ──────────────────────────────────────────────
     * USER:  POST /api/items/{itemId}/toggle/{tripId}
     *        → поміняти “взяв / не взяв” у конкретній подорожі
     * ────────────────────────────────────────────── */
    #[Route('/{itemId}/toggle/{tripId}', name: 'toggle', methods: ['POST'])]
    public function toggleTaken(int $itemId, int $tripId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([], 401);
        }

        /** @var Item|null $item */
        $item = $this->em->find(Item::class, $itemId);
        /** @var Trip|null $trip */
        $trip = $this->em->find(Trip::class, $tripId);

        if (!$item || !$trip || $trip->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $repo = $this->em->getRepository(TripItem::class);
        /** @var TripItem|null $ti */
        $ti = $repo->findOneBy(['trip' => $trip, 'item' => $item]);

        if (!$ti) {
            $ti = (new TripItem())
                ->setTrip($trip)
                ->setItem($item)
                ->setTaken(true);
            $this->em->persist($ti);
        } else {
            $ti->setTaken(!$ti->isTaken());
        }

        $this->em->flush();

        return $this->json(['taken' => $ti->isTaken()]);
    }
}
