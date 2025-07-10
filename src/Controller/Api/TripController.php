<?php
// src/Controller/Api/TripController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Weather;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * TripController – версія з Open-Meteo (до 16 днів, безкоштовно)
 * ---------------------------------------------------------------
 * ▸ Для погодних даних використовуємо api.open-meteo.com.
 * ▸ Сервіс приймає latitude/longitude ⇒ потрібен геокодер.
 *   Беремо Nominatim (OSM) – він також безкоштовний.
 */
#[Route('/api/trips', name: 'api_trips_')]
class TripController extends AbstractController
{
    private HttpClientInterface $http;
    private TripRepository      $tripRepo;

    public function __construct(HttpClientInterface $http, TripRepository $tripRepo)
    {
        $this->http     = $http;
        $this->tripRepo = $tripRepo;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        $user  = $this->getUser();
        $trips = $this->tripRepo->findBy(['user' => $user], ['startDate' => 'DESC']);

        $data = array_map(fn(Trip $t) => [
            'id'        => $t->getId(),
            'city'      => $t->getCity(),
            'country'   => $t->getCountry(),
            'startDate' => $t->getStartDate()?->format('Y-m-d'),
            'endDate'   => $t->getEndDate()?->format('Y-m-d'),
        ], $trips);

        return $this->json($data);
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

    /* --------------------------------------------------------------------- */
    /*                     ≡   О Н О В Л Е Н Н Я   П О Г О Д И               */
    /* --------------------------------------------------------------------- */
    #[Route('/{id}/weather/update', name: 'weather_update', methods: ['PATCH'])]
    public function updateWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        // 1) Перевіряємо доступ до поїздки
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        // 2) Геокодуємо місто через Nominatim
        $coord = $this->geocodeCity($trip->getCity(), $trip->getCountry());
        if (!$coord) {
            return $this->json(['error' => 'Не вдалося визначити координати'], 502);
        }

        // 3) Запит до Open-Meteo (до 16 днів)
        $resp = $this->http->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude'      => $coord['lat'],
                'longitude'     => $coord['lon'],
                'daily'         => 'temperature_2m_max,temperature_2m_min,weathercode',
                'timezone'      => 'UTC',
                'forecast_days' => 16,
            ],
            'headers' => [ 'User-Agent' => 'TravelApp (+https://your-app.example)' ],
        ]);

        if ($resp->getStatusCode() !== 200) {
            return $this->json(['error' => 'It was not possible to make the forecast'], 502);
        }

        $raw  = $resp->toArray()['daily'];
        $days = $this->mergeDailyArrays($raw);

        // 4) Зберігаємо у Weather
        $weather = $trip->getWeather() ?? (new Weather())->setTrip($trip);
        if (!$weather->getId()) {
            $trip->setWeather($weather);
            $em->persist($weather);
        }

        $today = $days[0];
        $weather
            ->setForecast($days)
            ->setTemperature($today['temp']['day'])
            ->setWeatherDescription($today['weather'][0]['description'])
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->json([
            'updatedAt'   => $weather->getUpdatedAt()->format('Y-m-d H:i:s'),
            'temperature' => $weather->getTemperature(),
            'description' => $weather->getWeatherDescription(),
            'forecast'    => $days,
        ]);
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
    /* --------------------------------------------------------------------- */
    /*                     ≡   О Т Р И М А Н Н Я   П О Г О Д И               */
    /* --------------------------------------------------------------------- */
    #[Route('/{id}/weather', name: 'weather', methods: ['GET'])]
    public function getWeather(int $id): JsonResponse
    {
        $trip = $this->tripRepo->find($id);
        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $weather = $trip->getWeather();
        if (!$weather || empty($weather->getForecast())) {
            return $this->json([]);
        }

        $start = $trip->getStartDate();
        $end   = $trip->getEndDate();
        $out   = [];

        foreach ($weather->getForecast() as $day) {
            $d = (new \DateTimeImmutable())->setTimestamp($day['dt']);
            if (!$start || !$end || ($d >= $start && $d <= $end)) {
                $out[] = $day;
            }
        }

        if (!$out) {
            $out = array_slice($weather->getForecast(), 0, 16);
        }

        return $this->json($out);
    }

    // ================================================
    //             ВНУТРІШНІ МЕТОДИ
    // ================================================

    private function geocodeCity(string $city, string $country): ?array
    {
        $resp = $this->http->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'city'    => $city,
                'country' => $country,
                'format'  => 'json',
                'limit'   => 1,
            ],
            'headers' => [ 'User-Agent' => 'TravelApp (+https://your-app.example)' ],
        ]);

        $arr = $resp->toArray();
        return $arr ? ['lat' => (float)$arr[0]['lat'], 'lon' => (float)$arr[0]['lon']] : null;
    }

    private function mergeDailyArrays(array $raw): array
    {
        $out   = [];
        $codes = $raw['weathercode'];
        foreach ($raw['time'] as $i => $date) {
            $code = $codes[$i];
            $out[] = [
                'dt'      => (new \DateTimeImmutable($date))->getTimestamp(),
                'temp'    => [
                    'day'   => $raw['temperature_2m_max'][$i],
                    'night' => $raw['temperature_2m_min'][$i],
                ],
                'weather' => [[
                    'description' => $this->weatherCodeToText($code),
                    'icon'        => $this->weatherCodeToIcon($code),
                ]],
            ];
        }
        return $out;
    }

    private function weatherCodeToText(int $c): string
    {
        $map = [
            0 => 'Clear sky',  1 => 'Mainly clear',  2 => 'Partly cloudy',  3 => 'Overcast',
            45 => 'Fog',       48 => 'Depositing rime fog',
            51 => 'Drizzle',   61 => 'Rain',          71 => 'Snow',         80 => 'Rain showers',
            95 => 'Thunderstorm',
        ];
        return $map[$c] ?? 'Unknown';
    }

    private function weatherCodeToIcon(int $c): string
    {
        $map = [
            0 => '01d', 1 => '02d', 2 => '03d', 3 => '04d',
            45 => '50d', 48 => '50d',
            51 => '09d', 61 => '10d', 71 => '13d', 80 => '09d', 95 => '11d',
        ];
        return $map[$c] ?? '01d';
    }
}
