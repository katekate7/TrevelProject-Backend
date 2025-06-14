<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CityController extends AbstractController
{
    #[Route('/api/cities', name: 'city_search', methods: ['GET'])]
    public function search(Request $request, HttpClientInterface $http): JsonResponse
    {
        $query = $request->query->get('q');
        if (!$query) {
            return $this->json([]);
        }

        $response = $http->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 5,
            ],
            'headers' => [
                'User-Agent' => 'MyTravelApp/1.0 (contact@email.com)',
            ],
        ]);

        $data = $response->toArray();

        $results = array_map(function ($item) {
            return [
                'label' => $item['display_name'],
                'city' => $item['address']['city'] ?? $item['address']['town'] ?? $item['address']['village'] ?? '',
                'country' => $item['address']['country'] ?? '',
            ];
        }, $data);

        return $this->json($results);
    }
}
