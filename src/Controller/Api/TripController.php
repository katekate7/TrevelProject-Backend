<?php

namespace App\Controller\Api;

use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TripController extends AbstractController
{
    #[Route('/api/trips/add', name: 'add_trip', methods: ['POST'])]
    public function addTrip(
        Request $request,
        EntityManagerInterface $em,
        HttpClientInterface $http
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        // Валідація даних (опційно)

        $trip = new Trip();
        $trip->setUser($this->getUser())
            ->setCountry($data['country'])
            ->setCity($data['city']);

        // --- fetch Wikipedia ---
        $title = urlencode($data['city']);
        $wiki = $http->request('GET', "https://en.wikipedia.org/api/rest_v1/page/summary/{$title}");
        if ($wiki->getStatusCode() === 200) {
            $info = $wiki->toArray();

            // Обрізаємо опис до 400 символів
            $description = $info['extract'] ?? null;
            if ($description !== null) {
                $description = mb_substr($description, 0, 400);
                $trip->setDescription($description);
            }

            // Додаємо зображення, якщо є
            if (!empty($info['thumbnail']['source'])) {
                $trip->setImageUrl($info['thumbnail']['source']);
            }
        }

        $em->persist($trip);
        $em->flush();

        return $this->json([
            'message' => 'Поїздку створено успішно',
            'trip' => [
                'id' => $trip->getId(),
                'country' => $trip->getCountry(),
                'city' => $trip->getCity(),
                'description' => $trip->getDescription(),
                'imageUrl' => $trip->getImageUrl(),
            ],
        ], 201);
    }

    #[Route('/api/trips/{id}', name: 'get_trip', methods: ['GET'])]
    public function getTrip(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Не знайдено'], 404);
        }

        return $this->json([
            'id' => $trip->getId(),
            'country' => $trip->getCountry(),
            'city' => $trip->getCity(),
            'description' => $trip->getDescription(),
            'imageUrl' => $trip->getImageUrl(),
        ]);
    }
    #[Route('/api/trips', name: 'get_all_trips', methods: ['GET'])]
    public function getAllTrips(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $trips = $em->getRepository(Trip::class)->findBy(['user' => $user]);

        $data = array_map(function(Trip $trip) {
            return [
                'id' => $trip->getId(),
                'country' => $trip->getCountry(),
                'city' => $trip->getCity(),
                'description' => $trip->getDescription(),
                'imageUrl' => $trip->getImageUrl(),
            ];
        }, $trips);

        return $this->json($data);
    }

}
