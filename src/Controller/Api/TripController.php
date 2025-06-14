<?php
// …
namespace App\Controller\Api;

use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;


class TripController extends AbstractController
{
    #[Route('/api/trips/add', name: 'add_trip', methods: ['POST'])]
    public function addTrip(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['country']) || empty($data['city'])) {
            return $this->json(['error' => 'Вкажіть і країну, і місто'], 400);
        }

        $trip = new Trip();
        $trip
            ->setUser($this->getUser())
            ->setCountry($data['country'])
            ->setCity($data['city']);

        $em->persist($trip);
        $em->flush();

        return $this->json([
            'message' => 'Поїздку створено успішно',
            'trip' => [
                'id'      => $trip->getId(),
                'country' => $trip->getCountry(),
                'city'    => $trip->getCity(),
            ],
        ], 201);
    }

    #[Route('/api/trips', name: 'get_trips', methods: ['GET'])]
    public function getTrips(EntityManagerInterface $em, SerializerInterface $serializer): JsonResponse
    {
        $trips = $em->getRepository(Trip::class)->findBy(['user' => $this->getUser()]);

        $json = $serializer->serialize($trips, 'json', [
            'circular_reference_handler' => function ($object) {
                return $object->getId(); // або null
            },
        ]);

        return new JsonResponse($json, 200, [], true); // true означає, що це вже JSON
    }

}
