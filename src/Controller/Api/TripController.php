<?php
// src/Controller/Api/TripController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Weather;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
// Додайте цей імпорт:
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;


#[Route('/api/trips', name: 'api_trips_')]
class TripController extends AbstractController
{
    private HttpClientInterface $http;
    private string              $weatherApiKey;
    private TripRepository      $tripRepo;

    public function __construct(
        HttpClientInterface $http,
        string $weatherApiKey,
        TripRepository $tripRepo
    ) {
        $this->http          = $http;
        $this->weatherApiKey = $weatherApiKey;
        $this->tripRepo      = $tripRepo;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user  = $this->getUser();
        $trips = $this->tripRepo->findBy(
            ['user' => $user],
            ['startDate' => 'DESC']
        );

        $data = array_map(fn(Trip $t) => [
            'id'        => $t->getId(),
            'city'      => $t->getCity(),
            'country'   => $t->getCountry(),
            'startDate' => $t->getStartDate()->format('Y-m-d'),
            'endDate'   => $t->getEndDate()->format('Y-m-d'),
        ], $trips);

        return $this->json($data, 200);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function addTrip(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['country', 'city', 'startDate', 'endDate'] as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Missing field: $field"], 400);
            }
        }

        $trip = (new Trip())
            ->setUser($this->getUser())
            ->setCountry($data['country'])
            ->setCity($data['city'])
            ->setStartDate(new \DateTimeImmutable($data['startDate']))
            ->setEndDate(new \DateTimeImmutable($data['endDate']));

        $em->persist($trip);
        $em->flush();

        return $this->json(['id' => $trip->getId()], 201);
    }


    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function deleteTrip(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(
                ['error' => 'Поїздку не знайдено'],
                Response::HTTP_NOT_FOUND
            );
        }

        $em->remove($trip);
        $em->flush();

        // Повертаємо порожній JSON з кодом 204
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id<\d+>}', name: 'get', methods: ['GET'])]
    public function getTrip(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $wikiDesc = null;
        $wikiImageUrl = null;
        try {
            $resp = $this->http->request(
                'GET',
                'https://en.wikipedia.org/api/rest_v1/page/summary/' . urlencode($trip->getCity())
            );
            if ($resp->getStatusCode() === 200) {
                $wiki = $resp->toArray();
                $wikiDesc     = $wiki['extract'] ?? null;
                $wikiImageUrl = $wiki['thumbnail']['source'] ?? null;
            }
        } catch (\Exception) {}

        return $this->json([
            'id'          => $trip->getId(),
            'country'     => $trip->getCountry(),
            'city'        => $trip->getCity(),
            'startDate'   => $trip->getStartDate()->format('Y-m-d'),
            'endDate'     => $trip->getEndDate()->format('Y-m-d'),
            'description' => $trip->getDescription() ?? $wikiDesc,
            'imageUrl'    => $trip->getImageUrl()    ?? $wikiImageUrl,
        ], 200);
    }

    #[Route('/{id}/sightseeings', name: 'sightseeings_update', methods: ['PATCH'])]
    public function updateSightseeings(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $data   = json_decode($request->getContent(), true);
        $titles = $data['titles'] ?? null;
        if (!is_array($titles) || empty($titles)) {
            return $this->json(['error' => 'titles must be a non-empty array'], 400);
        }

        $clean = array_map(fn($t) => trim(strip_tags($t)), $titles);
        $trip->setSightseeings(implode(', ', $clean));
        $em->flush();

        return $this->json(['saved' => true], 200);
    }

    #[Route('/{id}/weather', name: 'weather', methods: ['GET'])]
    public function getWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $weather = $trip->getWeather();
        if (!$weather || empty($weather->getForecast())) {
            return $this->json([], 200);
        }

        $forecast = $weather->getForecast();
        $start    = $trip->getStartDate();
        $end      = $trip->getEndDate();
        $out      = [];

        foreach ($forecast as $day) {
            $d = new \DateTimeImmutable($day['date']);
            if ($d >= $start && $d <= $end) {
                $out[] = $this->formatDay($day);
            }
        }

        if (empty($out)) {
            $slice = array_slice($forecast, 0, min(10, count($forecast)));
            foreach ($slice as $day) {
                $out[] = $this->formatDay($day);
            }
        }

        return $this->json($out, 200);
    }

    #[Route('/{id}/route', name: 'route_get', methods: ['GET'])]
    public function getRoute(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $route = $trip->getRoute();
        if (!$route) {
            return $this->json(['error' => 'Route not found'], 404);
        }

        $wps = array_map(fn($w) => [
            'id'    => $w->getId(),
            'title' => $w->getTitle(),
            'lat'   => $w->getLat(),
            'lng'   => $w->getLng(),
        ], $route->getWaypoints()->toArray());

        return $this->json([
            'id'        => $route->getId(),
            'tripId'    => $trip->getId(),
            'waypoints' => $wps,
        ], 200);
    }

    #[Route('/{id}/weather/update', name: 'weather_update', methods: ['PATCH'])]
    public function updateWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $daysNeeded = $trip->getEndDate()->diff($trip->getStartDate())->days + 1;
        $daysToAsk  = max(10, min(10, $daysNeeded));

        $resp = $this->http->request('GET', 'http://api.weatherapi.com/v1/forecast.json', [
            'query' => [
                'key'  => $this->weatherApiKey,
                'q'    => "{$trip->getCity()},{$trip->getCountry()}",
                'days' => $daysToAsk,
            ],
        ]);

        if ($resp->getStatusCode() !== 200) {
            return $this->json(['error' => 'Не вдалося отримати прогноз'], 502);
        }

        $forecastRaw = $resp->toArray()['forecast']['forecastday'];
        $today       = $forecastRaw[0]['day'];

        $weather = $trip->getWeather() ?? (new Weather())->setTrip($trip);
        if (!$weather->getId()) {
            $trip->setWeather($weather);
            $em->persist($weather);
        }

        $weather
            ->setForecast($forecastRaw)
            ->setTemperature($today['avgtemp_c'])
            ->setHumidity($today['avghumidity'])
            ->setWeatherDescription($today['condition']['text'])
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->json([
            'updatedAt'   => $weather->getUpdatedAt()->format('Y-m-d H:i:s'),
            'temperature' => $weather->getTemperature(),
            'humidity'    => $weather->getHumidity(),
            'description' => $weather->getWeatherDescription(),
            'forecast'    => $forecastRaw,
        ], 200);
    }

    private function formatDay(array $day): array
    {
        return [
            'dt'   => $day['date_epoch'],
            'temp' => [
                'day'   => $day['day']['avgtemp_c'],
                'night' => $day['day']['mintemp_c'],
            ],
            'weather' => [[
                'description' => $day['day']['condition']['text'],
                'icon'        => pathinfo($day['day']['condition']['icon'], PATHINFO_FILENAME),
            ]],
        ];
    }
    #[Route('/{id<\d+>}', name: 'update_dates', methods: ['PATCH'])]
    public function updateDates(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse
    {
        // 1) знаходимо поїздку
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        // 2) валідуємо body
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['startDate']) || empty($data['endDate'])) {
            return $this->json(['error' => 'startDate і endDate обовʼязкові'], 400);
        }

        try {
            $trip->setStartDate(new \DateTimeImmutable($data['startDate']));
            $trip->setEndDate  (new \DateTimeImmutable($data['endDate']));
        } catch (\Throwable) {
            return $this->json(['error' => 'Неправильний формат дати'], 400);
        }

        $em->flush();                    // ← дати збережені

        // 3) одразу оновлюємо прогноз (використовуємо вже наявний endpoint)
        //    можемо викликати внутрішньо той самий метод, аби не плодити код:
        $this->forward(__CLASS__.'::updateWeather', ['id' => $id]);

        // 4) повертаємо оновлений об'єкт
        return $this->json([
            'id'        => $trip->getId(),
            'city'      => $trip->getCity(),
            'country'   => $trip->getCountry(),
            'startDate' => $trip->getStartDate()->format('Y-m-d'),
            'endDate'   => $trip->getEndDate()->format('Y-m-d'),
        ]);
    }

}
