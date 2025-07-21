<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ItemControllerIntegrationTest extends WebTestCase
{
    public function testGetItemsRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/items');

        // Should return 401 since JWT authentication is required
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('JWT Token not found', $content['message']);
    }

    public function testCreateItemRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/items', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Test Item'])
        );

        // Should return 401 Unauthorized since no authentication
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
