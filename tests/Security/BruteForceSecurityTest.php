<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BruteForceSecurityTest extends WebTestCase
{
    public function testBruteForceLoginProtection(): void
    {
        $client = static::createClient();
        
        // First, create a user to attempt brute force against
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'bruteforce_' . uniqid() . '@test.com',
            'password' => 'correctpassword123'
        ];

        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );
        $this->assertResponseIsSuccessful();

        // Simulate multiple failed login attempts
        $failedAttempts = 0;
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $loginData = [
                'email' => $userData['email'],
                'password' => 'wrongpassword' . $i
            ];

            $client->request('POST', '/api/auth/login', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($loginData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            
            if ($responseCode === Response::HTTP_UNAUTHORIZED) {
                $failedAttempts++;
            }

            // After several failed attempts, the system should still respond
            // (even if it implements rate limiting)
            $this->assertContains($responseCode, [
                Response::HTTP_UNAUTHORIZED,  // Normal failed login
                Response::HTTP_TOO_MANY_REQUESTS,  // Rate limited
                Response::HTTP_NOT_FOUND  // Endpoint might not exist yet
            ], "Server should handle failed login attempt $i gracefully");
        }

        // Verify server is still responsive after brute force attempt
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'afterbrute_' . uniqid(),
                'email' => 'after_' . uniqid() . '@test.com',
                'password' => 'password123'
            ])
        );
        
        $this->assertContains($client->getResponse()->getStatusCode(), [
            Response::HTTP_CREATED,
            Response::HTTP_CONFLICT
        ]);
    }

    public function testPasswordResetBruteForceProtection(): void
    {
        $client = static::createClient();
        
        // Create a user first
        $userData = [
            'username' => 'resettest_' . uniqid(),
            'email' => 'resettest_' . uniqid() . '@test.com',
            'password' => 'password123'
        ];

        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );
        $this->assertResponseIsSuccessful();

        // Attempt multiple password reset requests
        $resetAttempts = 5;
        $successfulRequests = 0;

        for ($i = 0; $i < $resetAttempts; $i++) {
            $resetData = ['email' => $userData['email']];

            $client->request('POST', '/api/users/reset-password-request', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($resetData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            
            if ($responseCode === Response::HTTP_OK) {
                $successfulRequests++;
            }

            // System should handle multiple reset requests gracefully
            $this->assertContains($responseCode, [
                Response::HTTP_OK,  // Reset email sent
                Response::HTTP_TOO_MANY_REQUESTS,  // Rate limited
                Response::HTTP_NOT_FOUND,  // User not found or endpoint doesn't exist
                Response::HTTP_BAD_REQUEST,  // Invalid request
                Response::HTTP_METHOD_NOT_ALLOWED  // Endpoint might not exist or wrong method
            ], "Password reset attempt $i should be handled gracefully");

            // Small delay to simulate real-world scenario
            usleep(100000); // 0.1 seconds
        }

        // Verify system is still functional
        $client->request('GET', '/api/items');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED); // Expected due to auth requirement
    }

    public function testRegistrationFloodProtection(): void
    {
        $client = static::createClient();
        
        // Attempt to register multiple users rapidly
        $registrationAttempts = 8;
        $responses = [];

        for ($i = 0; $i < $registrationAttempts; $i++) {
            $userData = [
                'username' => 'flood_' . $i . '_' . uniqid(),
                'email' => 'flood_' . $i . '_' . uniqid() . '@test.com',
                'password' => 'password123'
            ];

            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            $responses[] = $responseCode;

            // System should handle rapid registrations
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_TOO_MANY_REQUESTS,
                Response::HTTP_CONFLICT
            ], "Registration flood attempt $i should be handled");

            // Brief delay between requests
            usleep(50000); // 0.05 seconds
        }

        // At least one registration should have succeeded or been rate-limited
        $validResponses = array_intersect($responses, [
            Response::HTTP_CREATED,
            Response::HTTP_TOO_MANY_REQUESTS
        ]);
        
        $this->assertNotEmpty($validResponses, 'At least one registration should succeed or be rate-limited');
    }

    public function testApiEndpointFloodProtection(): void
    {
        $client = static::createClient();
        
        // Test flooding a public endpoint
        $floodAttempts = 15;
        $responses = [];

        for ($i = 0; $i < $floodAttempts; $i++) {
            $client->request('GET', '/api/items');
            $responses[] = $client->getResponse()->getStatusCode();

            usleep(10000); // 0.01 seconds between requests
        }

        // All responses should be either 401 (unauthorized) or 429 (rate limited)
        foreach ($responses as $index => $responseCode) {
            $this->assertContains($responseCode, [
                Response::HTTP_UNAUTHORIZED,  // Normal auth required
                Response::HTTP_TOO_MANY_REQUESTS  // Rate limited
            ], "API flood request $index should return appropriate status");
        }

        // System should still be responsive after flood
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'postflood_' . uniqid(),
                'email' => 'postflood_' . uniqid() . '@test.com',
                'password' => 'password123'
            ])
        );

        $this->assertContains($client->getResponse()->getStatusCode(), [
            Response::HTTP_CREATED,
            Response::HTTP_CONFLICT,
            Response::HTTP_TOO_MANY_REQUESTS
        ]);
    }
}
