<?php
// src/Controller/Api/TripController.php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\Weather;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/trips', name: 'api_trips_')]
class TripController extends AbstractController
{
    private HttpClientInterface $http;
    private string $weatherApiKey;

    public function __construct(HttpClientInterface $http, string $weatherApiKey)
    {
        $this->http = $http;
        $this->weatherApiKey = $weatherApiKey;
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getTrip(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);

        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        // спробуємо витягти опис і картинку з Вікіпедії
        $wikiDesc     = null;
        $wikiImageUrl = null;

        try {
            $wikiResp = $this->http->request(
                'GET',
                'https://en.wikipedia.org/api/rest_v1/page/summary/' . urlencode($trip->getCity())
            );

            if ($wikiResp->getStatusCode() === 200) {
                $wiki = $wikiResp->toArray();
                $wikiDesc     = $wiki['extract'] ?? null;
                $wikiImageUrl = $wiki['thumbnail']['source'] ?? null;
            }
        } catch (\Exception $e) {
            // якщо щось пішло не так — пропускаємо
        }

        // повертаємо або власний опис/картинку, або з Вікі
        return $this->json([
            'id'          => $trip->getId(),
            'country'     => $trip->getCountry(),
            'city'        => $trip->getCity(),
            'startDate'   => $trip->getStartDate()?->format('Y-m-d'),
            'endDate'     => $trip->getEndDate()?->format('Y-m-d'),
            'description' => $trip->getDescription() ?? $wikiDesc,
            'imageUrl'    => $trip->getImageUrl()    ?? $wikiImageUrl,
        ], 200);
    }

    #[Route('/{id}/weather', name: 'weather', methods: ['GET'])]
    public function getWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);

        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $w = $trip->getWeather();
        if (!$w || empty($w->getForecast())) {
            return $this->json([]);
        }

        $out = [];
        foreach ($w->getForecast() as $day) {
            $d = new \DateTimeImmutable($day['date']);
            if ($d < $trip->getStartDate() || $d > $trip->getEndDate()) {
                continue;
            }

            $out[] = [
                'dt'      => $day['date_epoch'],
                'temp'    => [
                    'day'   => $day['day']['avgtemp_c'],
                    'night' => $day['day']['mintemp_c'],
                ],
                'weather' => [[
                    'description' => $day['day']['condition']['text'],
                    'icon'        => pathinfo($day['day']['condition']['icon'], PATHINFO_FILENAME),
                ]]
            ];
        }

        return $this->json($out, 200);
    }

    #[Route('/{id}/weather/update', name: 'weather_update', methods: ['PATCH'])]
    public function updateWeather(int $id, EntityManagerInterface $em): JsonResponse
    {
        $trip = $em->getRepository(Trip::class)->find($id);

        if (!$trip || $trip->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Поїздку не знайдено'], 404);
        }

        $days = min(10, $trip->getEndDate()->diff($trip->getStartDate())->days + 1);
        $resp = $this->http->request('GET', 'http://api.weatherapi.com/v1/forecast.json', [
            'query' => [
                'key'  => $this->weatherApiKey,
                'q'    => "{$trip->getCity()},{$trip->getCountry()}",
                'days' => $days,
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
}
