<?php

namespace App\Controller\Api;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ItemController extends AbstractController
{
    #[Route('/api/items', name: 'get_all_items', methods: ['GET'])]
    public function getAllItems(EntityManagerInterface $em): JsonResponse
    {
        $items = $em->getRepository(Item::class)->findAll();

        $data = array_map(function (Item $item) {
            return [
                'id' => $item->getId(),
                'name' => $item->getName(),
            ];
        }, $items);

        return $this->json($data);
    }

    #[Route('/api/admin/items', name: 'admin_items_create', methods: ['POST'])]
    public function addItem(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->json(['error' => 'Missing item name'], 400);
        }

        $item = new Item();
        $item->setName($data['name']);

        $em->persist($item);
        $em->flush();

        return $this->json([
            'message' => 'Item added successfully',
            'item' => [
                'id' => $item->getId(),
                'name' => $item->getName()
            ]
        ], 201);
    }

    #[Route('/api/admin/items/{id}', name: 'admin_items_update', methods: ['PATCH'])]
    public function updateItem(Item $item, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->json(['error' => 'Missing item name'], 400);
        }

        $item->setName($data['name']);
        $em->flush();

        return $this->json([
            'message' => 'Item updated successfully',
            'item' => [
                'id' => $item->getId(),
                'name' => $item->getName()
            ]
        ]);
    }

    #[Route('/api/admin/items/{id}', name: 'admin_items_delete', methods: ['DELETE'])]
    public function deleteItem(Item $item, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em->remove($item);
        $em->flush();

        return $this->json(['message' => 'Item deleted successfully']);
    }
}
