<?php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/trips')]
class TripController extends AbstractController
{
    #[Route('/', name: 'get_trips', methods: ['GET'])]
    public function getTrips(TripRepository $tripRepository): JsonResponse
    {
        return $this->json($tripRepository->findBy(['user' => $this->getUser()]));
    }

    #[Route('/add', name: 'add_trip', methods: ['POST'])]
    public function addTrip(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $trip = new Trip();
        $trip->setUser($this->getUser());
        $trip->setDestination($data['destination']);
        $trip->setStartDate(new \DateTime($data['start_date']));
        $trip->setEndDate(new \DateTime($data['end_date']));

        $entityManager->persist($trip);
        $entityManager->flush();

        return $this->json(['message' => 'Trip added successfully']);
    }
}
