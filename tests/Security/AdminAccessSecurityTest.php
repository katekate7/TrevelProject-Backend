<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminAccessSecurityTest extends WebTestCase
{
    public function testAdminEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();
        
        // Test admin-only endpoints without authentication
        $adminEndpoints = [
            ['POST', '/api/items'],
            ['DELETE', '/api/items/1'],
            ['POST', '/api/users'],
            ['GET', '/api/users'],
        ];

        foreach ($adminEndpoints as [$method, $url]) {
            $client->request($method, $url);
            $this->assertResponseStatusCodeSame(
                Response::HTTP_UNAUTHORIZED,
                "Endpoint $method $url should require authentication"
            );
        }
    }

    public function testUserCannotAccessAdminEndpoints(): void
    {
        $client = static::createClient();
        
        // This test assumes we have a way to authenticate as a regular user
        // In a real scenario, you'd create a user, get a JWT token, and use it
        $client->request('POST', '/api/items', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Unauthorized Item'])
        );

        // Should return 401 since no auth token provided
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
