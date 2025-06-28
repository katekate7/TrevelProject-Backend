<?php
// src/Controller/Api/TripPlaceController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/trips/{trip<\d+>}/places', name:'api_trip_places_')]
class TripPlaceController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name:'list', methods:['GET'])]
    public function list(Trip $trip): JsonResponse
    {
        $out = array_map(fn(Place $p)=>[
            'id'    => $p->getId(),
            'title' => $p->getName(),
            'lat'   => $p->getLat(),
            'lng'   => $p->getLng(),
        ], $trip->getPlaces()->toArray());

        return $this->json($out);
    }

    #[Route('', name:'save', methods:['POST'])]
    public function save(Trip $trip, Request $req): JsonResponse
    {
        $body = json_decode($req->getContent(), true);
        $wps  = $body['waypoints'] ?? [];

        // очистити старі
        foreach($trip->getPlaces() as $old) {
            $this->em->remove($old);
        }
        $trip->getPlaces()->clear();

        // додати нові
        foreach($wps as $wp) {
            $p = (new Place())
                ->setTrip($trip)
                ->setName($wp['title'])
                ->setLat((float)$wp['lat'])
                ->setLng((float)$wp['lng']);
            $this->em->persist($p);
        }

        $this->em->flush();
        return $this->json(['saved'=>true]);
    }
}
