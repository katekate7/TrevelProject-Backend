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

        // скільки днів прогнозу нам треба (WeatherAPI максимум 10)
        $days = min(10, $end->diff($start)->days + 1);

        // запит Forecast на весь період поїздки
        $resp = $this->http->request('GET', 'http://api.weatherapi.com/v1/forecast.json', [
            'query' => [
                'key'  => $this->weatherApiKey,
                'q'    => "{$data['city']},{$data['country']}",
                'days' => $days,
            ],
        ]);

        if ($resp->getStatusCode() === 200) {
            $forecastRaw = $resp->toArray()['forecast']['forecastday'];

            $weather = (new Weather())
                ->setTrip($trip)
                ->setForecast($forecastRaw)
                ->setUpdatedAt(new \DateTimeImmutable());

            $trip->setWeather($weather);
            $em->persist($weather);
        }

        $em->persist($trip);
        $em->flush();

        return $this->json(['id' => $trip->getId()], 201);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listTrips(EntityManagerInterface $em): JsonResponse
    {
        $trips = $em->getRepository(Trip::class)
                    ->findBy(['user' => $this->getUser()]);

        return $this->json($trips, 200, [], [
            'circular_reference_handler' => fn($obj)=>$obj->getId(),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getTrip(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error'=>'Поїздку не знайдено'], 404);
        }

        return $this->json([
            'id'          => $trip->getId(),
            'country'     => $trip->getCountry(),
            'city'        => $trip->getCity(),
            'startDate'   => $trip->getStartDate()->format('Y-m-d'),
            'endDate'     => $trip->getEndDate()->format('Y-m-d'),
            'description' => $trip->getDescription(),
            'imageUrl'    => $trip->getImageUrl(),
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
        if (!$w || empty($w->getForecast())) {
            return $this->json([]);
        }

        $out = [];
        foreach ($w->getForecast() as $day) {
            // перетворюємо дату прогнозу в DateTime та фільтруємо за періодом поїздки
            $d = new \DateTimeImmutable($day['date']);
            if ($d < $trip->getStartDate() || $d > $trip->getEndDate()) {
                continue;
            }

            $out[] = [
                'dt'   => $day['date_epoch'],
                'temp' => [
                    'day'   => $day['day']['avgtemp_c'],
                    'night' => $day['day']['mintemp_c'],
                ],
                'weather' => [[
                    'description' => $day['day']['condition']['text'],
                    'icon'        => pathinfo($day['day']['condition']['icon'], PATHINFO_FILENAME),
                ]]
            ];
        }

        return $this->json($out);
    }
}
