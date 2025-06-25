<?php

namespace App\Controller\Api;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    /* ───────────────────────────────────────────
     * PUBLIC:  GET /api/items   → list for everyone
     * ─────────────────────────────────────────── */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $items = $em->getRepository(Item::class)
                    ->createQueryBuilder('i')
                    ->orderBy('i.importanceLevel', 'ASC')
                    ->getQuery()
                    ->getResult();

        $data = \array_map(fn(Item $i) => [
            'id'   => $i->getId(),
            'name' => $i->getName(),
            'importanceLevel' => $i->getImportanceLevel(),
        ], $items);

        return $this->json($data, 200);
    }

    /* ───────────────────────────────────────────
     * ADMIN:  POST /api/items        (create)
     * ─────────────────────────────────────────── */
    #[Route('', name: 'create', methods: ['POST'])]
    public function add(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Missing item name'], 400);
        }

        $item = (new Item())
            ->setName($data['name'])
            ->setImportanceLevel((int)($data['importanceLevel'] ?? 5));

        $em->persist($item);
        $em->flush();

        return $this->json([
            'message' => 'Item created',
            'item'    => [
                'id'   => $item->getId(),
                'name' => $item->getName(),
                'importanceLevel' => $item->getImportanceLevel(),
            ],
        ], 201);
    }

    /* ───────────────────────────────────────────
     * ADMIN:  PATCH /api/items/{id}  (update)
     * ─────────────────────────────────────────── */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(
        Item $item,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $item->setName($data['name']);
        }
        if (isset($data['importanceLevel'])) {
            $item->setImportanceLevel((int)$data['importanceLevel']);
        }

        $em->flush();

        return $this->json([
            'message' => 'Item updated',
            'item' => [
                'id'   => $item->getId(),
                'name' => $item->getName(),
                'importanceLevel' => $item->getImportanceLevel(),
            ],
        ], 200);
    }

    /* ───────────────────────────────────────────
     * ADMIN:  DELETE /api/items/{id}
     * ─────────────────────────────────────────── */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Item $item, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em->remove($item);
        $em->flush();

        return $this->json(['message' => 'Item deleted'], 200);
    }
}
