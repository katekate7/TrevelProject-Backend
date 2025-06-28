<?php
// src/Controller/Api/TripItemController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Item;
use App\Entity\TripItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/trips/{tripId}/items', name: 'api_trip_items_')]
class TripItemController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * GET  /api/trips/{tripId}/items
     * повертає [{ id, name, importanceLevel, isChecked }, …]
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $tripId): JsonResponse
    {
        $user = $this->getUser();
        $trip = $this->em->find(Trip::class, $tripId);
        if (!$trip || $trip->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = [];
        foreach ($trip->getTripItems() as $ti) {
            $item = $ti->getItem();
            $data[] = [
                'id'              => $item->getId(),
                'name'            => $item->getName(),
                'importanceLevel' => $item->getImportanceLevel(),
                'isChecked'       => $ti->isChecked(),
            ];
        }

        return $this->json($data, 200);
    }

    /**
     * POST /api/trips/{tripId}/items
     * { itemId } → переключає «взяв/не взяв»
     * повертає { isChecked: bool }
     */
    #[Route('', name: 'toggle', methods: ['POST'])]
    public function toggle(int $tripId, Request $request): JsonResponse
    {
        $user   = $this->getUser();
        $trip   = $this->em->find(Trip::class, $tripId);
        $payload= json_decode($request->getContent(), true);
        $itemId = $payload['itemId'] ?? null;

        if (!$user || !$trip || $trip->getUser() !== $user || !$itemId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $item = $this->em->find(Item::class, $itemId);
        if (!$item) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $repo = $this->em->getRepository(TripItem::class);
        $ti   = $repo->findOneBy(['trip' => $trip, 'item' => $item]);

        if (!$ti) {
            $ti = (new TripItem())
                ->setTrip($trip)
                ->setItem($item)
                ->setChecked(true);
            $this->em->persist($ti);
        } else {
            $ti->setChecked(!$ti->isChecked());
        }

        $this->em->flush();

        return $this->json(['isChecked' => $ti->isChecked()]);
    }
}
