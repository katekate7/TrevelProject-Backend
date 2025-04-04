<?php

namespace App\Controller\Api;

use App\Entity\Destination;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/destinations')]
class DestinationController extends AbstractController
{
    #[Route('/', name: 'get_destinations', methods: ['GET'])]
    public function getDestinations(DestinationRepository $destinationRepository): JsonResponse
    {
        return $this->json($destinationRepository->findAll());
    }

    #[Route('/{id}', name: 'get_destination', methods: ['GET'])]
    public function getDestination(Destination $destination): JsonResponse
    {
        return $this->json($destination);
    }

    #[Route('/add', name: 'add_destination', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]  // Доступ лише для користувачів (НЕ адміністраторів)
    public function addDestination(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['country'], $data['description'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $destination = new Destination();
        $destination->setName($data['name']);
        $destination->setCountry($data['country']);
        $destination->setDescription($data['description']);

        $entityManager->persist($destination);
        $entityManager->flush();

        return $this->json(['message' => 'Destination added successfully']);
    }
}
