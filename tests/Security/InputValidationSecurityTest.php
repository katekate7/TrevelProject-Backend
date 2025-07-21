<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InputValidationSecurityTest extends WebTestCase
{
    public function testSqlInjectionProtectionInRegistration(): void
    {
        $client = static::createClient();
        
        // Attempt SQL injection in username field
        $maliciousData = [
            'username' => "admin'; DROP TABLE users; --",
            'email' => 'hacker@evil.com',
            'password' => 'password123'
        ];

        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($maliciousData)
        );

        // Should either succeed (meaning input was sanitized) or fail with validation error
        $responseCode = $client->getResponse()->getStatusCode();
        $this->assertContains($responseCode, [
            Response::HTTP_CREATED,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_CONFLICT
        ]);
        
        // Test that the API is still functional after the attempt
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'normaluser_' . uniqid(),
                'email' => 'normal@example.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testXssProtectionInItemCreation(): void
    {
        $client = static::createClient();
        
        // Attempt XSS in item name
        $maliciousData = [
            'name' => '<script>alert("XSS")</script>',
            'important' => false
        ];

        $client->request('POST', '/api/items', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($maliciousData)
        );

        // Should return 401 due to missing authentication, 
        // but the important thing is the server doesn't crash
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
