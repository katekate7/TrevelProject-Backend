<?php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Weather;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/trips', name: 'api_trips_')]
class TripController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $http,
        private string $weatherApiKey
    ) {}

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function addTrip(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $start = new \DateTimeImmutable($data['startDate']);
        $end   = new \DateTimeImmutable($data['endDate']);

        $trip = (new Trip())
            ->setUser($this->getUser())
            ->setCountry($data['country'])
            ->setCity($data['city'])
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDescription($data['description'] ?? null)
            ->setImageUrl($data['imageUrl'] ?? null);

        // Free WeatherAPI.com — current.json
        $resp = $this->http->request('GET', 'http://api.weatherapi.com/v1/current.json', [
            'query' => [
                'key' => $this->weatherApiKey,
                'q'   => "{$data['city']},{$data['country']}",
            ],
        ]);

        if ($resp->getStatusCode() === 200) {
            $current = $resp->toArray()['current'];
            $weather = (new Weather())
                ->setTrip($trip)
                ->setTemperature($current['temp_c'])
                ->setHumidity($current['humidity'])
                ->setWeatherDescription($current['condition']['text'])
                ->setUpdatedAt(new \DateTimeImmutable());

            $trip->setWeather($weather);
            $em->persist($weather);
        }

        $em->persist($trip);
        $em->flush();

        return $this->json(['id' => $trip->getId()], 201);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listTrips(EntityManagerInterface $em, SerializerInterface $serializer): JsonResponse
    {
        $trips = $em->getRepository(Trip::class)
                    ->findBy(['user' => $this->getUser()]);

        $json = $serializer->serialize($trips, 'json', [
            'circular_reference_handler' => fn($obj)=>$obj->getId(),
        ]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getTrip(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error'=>'Поїздку не знайдено'], 404);
        }

        return $this->json([
            'id'        => $trip->getId(),
            'country'   => $trip->getCountry(),
            'city'      => $trip->getCity(),
            'startDate' => $trip->getStartDate()->format('Y-m-d'),
            'endDate'   => $trip->getEndDate()->format('Y-m-d'),
            'description'=> $trip->getDescription(),
            'imageUrl'   => $trip->getImageUrl(),
        ]);
    }

    #[Route('/{id}/weather', name: 'weather', methods: ['GET'])]
    public function getWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error'=>'Поїздку не знайдено'], 404);
        }
        $w = $trip->getWeather();
        if (!$w) {
            return $this->json([]);
        }
        return $this->json([
            'temperature' => $w->getTemperature(),
            'humidity'    => $w->getHumidity(),
            'description' => $w->getWeatherDescription(),
            'updatedAt'   => $w->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
