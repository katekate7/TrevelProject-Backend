<?php
// src/Controller/Api/TripRouteController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Trajet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/trips/{trip<\d+>}/route', name:'api_trip_route_')]
class TripRouteController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name:'get', methods:['GET'])]
    public function get(Trip $trip): JsonResponse
    {
        $t = $trip->getLastTrajet();
        if (!$t) {
            return $this->json([], 204);
        }
        return $this->json([
            'routeData' => $t->getRouteData(),
            'distance'  => $t->getDistance(),
            'createdAt' => $t->getCreatedAt()->format('c'),
        ]);
    }

    #[Route('', name:'save', methods:['POST'])]
    public function save(Trip $trip, Request $req): JsonResponse
    {
        $b    = json_decode($req->getContent(), true);
        $geo  = $b['routeData'] ?? [];
        $dist = isset($b['distance']) ? (float)$b['distance'] : null;

        $t = (new Trajet())
            ->setTrip($trip)
            ->setRouteData($geo)
            ->setDistance($dist);

        $this->em->persist($t);
        $this->em->flush();

        return $this->json(['saved'=>true,'id'=>$t->getId()]);
    }
}
