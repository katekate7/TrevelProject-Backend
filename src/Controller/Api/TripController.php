<?php
// …
namespace App\Controller\Api;

use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/trips')]
class TripController extends AbstractController
{
    #[Route('/add', name: 'add_trip', methods: ['POST'])]
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

    #[Route('/', name: 'get_trips', methods: ['GET'])]
    public function getTrips(): JsonResponse
    {
        $trips = $this->getDoctrine()
                      ->getRepository(Trip::class)
                      ->findBy(['user' => $this->getUser()]);

        return $this->json($trips);
    }
}
