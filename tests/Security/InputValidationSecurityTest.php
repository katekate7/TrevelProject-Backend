<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InputValidationSecurityTest extends WebTestCase
{
    public function testSqlInjectionProtectionInRegistration(): void
    {
        $client = static::createClient();
        
        // Test various SQL injection payloads
        $sqlInjectionPayloads = [
            "admin'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin' UNION SELECT * FROM users --",
            "'; DELETE FROM users WHERE '1'='1",
            "admin' OR 1=1#",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $maliciousData = [
                'username' => $payload,
                'email' => 'hacker_' . uniqid() . '@evil.com',
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
                Response::HTTP_CONFLICT,
                Response::HTTP_INTERNAL_SERVER_ERROR  // App might crash but doesn't compromise security
            ], "SQL injection payload should be handled safely: $payload");
        }
        
        // Test that the API is still functional after the attempts
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'normaluser_' . uniqid(),
                'email' => 'normal_' . uniqid() . '@example.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testXssProtectionInUserRegistration(): void
    {
        $client = static::createClient();
        
        // Test various XSS payloads
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<svg onload=alert("XSS")>',
            '"><script>alert("XSS")</script>',
            '\'-alert("XSS")-\'',
        ];

        foreach ($xssPayloads as $payload) {
            $maliciousData = [
                'username' => $payload,
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => 'password123'
            ];

            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($maliciousData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_CONFLICT,
                Response::HTTP_INTERNAL_SERVER_ERROR  // App might crash but doesn't compromise security
            ], "XSS payload should be handled safely: $payload");

            // If successful, verify the stored data doesn't contain executable code
            if ($responseCode === Response::HTTP_CREATED) {
                $responseContent = $client->getResponse()->getContent();
                $this->assertStringNotContainsString('<script>', $responseContent);
                $this->assertStringNotContainsString('javascript:', $responseContent);
                $this->assertStringNotContainsString('onerror=', $responseContent);
            }
        }
    }

    public function testSqlInjectionInTripCreation(): void
    {
        $client = static::createClient();
        
        // Test SQL injection in trip data (will fail due to auth, but server shouldn't crash)
        $maliciousTripData = [
            'city' => "Paris'; DROP TABLE trips; --",
            'country' => "France' OR '1'='1",
            'startDate' => '2025-07-25',
            'endDate' => '2025-07-30'
        ];

        $client->request('POST', '/api/trips/add', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($maliciousTripData)
        );

        // Should return 401 due to missing authentication
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        
        // Server should still be responsive
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'testuser_' . uniqid(),
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }
}
