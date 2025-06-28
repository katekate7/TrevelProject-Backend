<?php
// src/Controller/Api/TripItemController.php
namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Item;
use App\Entity\TripItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/trips/{tripId}/items', name: 'api_trip_items_')]
class TripItemController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // 1️⃣ — повертаємо для цієї поїздки всі речі з прапором taken
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $tripId): JsonResponse
    {
        $user = $this->getUser();
        $trip = $this->em->getRepository(Trip::class)->find($tripId);
        if (!$trip || $trip->getUser() !== $user) {
            return $this->json(['error'=>'Not found'], 404);
        }

        // всі глобальні речі
        $all = $this->em->getRepository(Item::class)
            ->findBy([], ['importanceLevel'=>'ASC']);

        // вже існуючі TripItem
        $tis = $this->em->getRepository(TripItem::class)
            ->findBy(['trip'=>$trip]);

        // скласти map itemId→taken
        $map = [];
        foreach ($tis as $ti) {
            $map[$ti->getItem()->getId()] = $ti->isChecked();
        }

        $out = [];
        foreach ($all as $i) {
            $out[] = [
                'id'              => $i->getId(),
                'name'            => $i->getName(),
                'importanceLevel' => $i->getImportanceLevel(),
                'isChecked'       => $map[$i->getId()] ?? false,
            ];
        }

        return $this->json($out);
    }

    // 2️⃣ — переключаємо взяту/не взяту
    #[Route('/{itemId}', name: 'toggle', methods: ['POST'])]
    public function toggle(int $tripId, int $itemId): JsonResponse
    {
        $user = $this->getUser();
        $trip = $this->em->getRepository(Trip::class)->find($tripId);
        $item = $this->em->getRepository(Item::class)->find($itemId);

        if (!$trip || $trip->getUser()!==$user || !$item) {
            return $this->json(['error'=>'Not found'], 404);
        }

        $repo = $this->em->getRepository(TripItem::class);
        $ti   = $repo->findOneBy(['trip'=>$trip,'item'=>$item]);

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
        return $this->json(['taken' => $ti->isChecked()]);
    }
}
