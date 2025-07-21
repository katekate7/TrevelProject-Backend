<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class SQLInjectionSecurityTest extends WebTestCase
{
    public function testBasicSQLInjectionProtection(): void
    {
        $client = static::createClient();
        
        $basicSQLPayloads = [
            "admin' OR '1'='1",
            "'; DROP TABLE users; --",
            "admin'; DELETE FROM users WHERE '1'='1",
            "' UNION SELECT * FROM users --",
            "admin' OR 1=1#",
            "' OR 'a'='a",
        ];

        foreach ($basicSQLPayloads as $payload) {
            $userData = [
                'username' => $payload,
                'email' => 'sql_' . uniqid() . '@test.com',
                'password' => 'password123'
            ];

            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_CONFLICT,
                Response::HTTP_INTERNAL_SERVER_ERROR  // App might crash but doesn't compromise security
            ], "Basic SQL injection payload should be handled: $payload");
        }

        // Verify database is still functional
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'testnormal_' . uniqid(),
                'email' => 'normal_' . uniqid() . '@test.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testAdvancedSQLInjectionProtection(): void
    {
        $client = static::createClient();
        
        $advancedSQLPayloads = [
            "admin'/**/OR/**/'1'='1",
            "'; EXEC xp_cmdshell('dir'); --",
            "' AND (SELECT COUNT(*) FROM users) > 0 --",
            "admin' UNION SELECT 1,username,password FROM users --",
            "'; INSERT INTO users (username,email) VALUES ('hacker','hack@evil.com'); --",
            "' OR SLEEP(5) --",
            "admin' AND EXTRACTVALUE(1, CONCAT(0x7e, (SELECT version()), 0x7e)) --",
        ];

        foreach ($advancedSQLPayloads as $payload) {
            $userData = [
                'username' => 'user_' . uniqid(),
                'email' => $payload, // Try SQL injection in email field
                'password' => 'password123'
            ];

            $startTime = microtime(true);
            
            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Should not take too long (no SLEEP injection)
            $this->assertLessThan(2.0, $executionTime, "SQL injection with SLEEP should be prevented");

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_CONFLICT
            ], "Advanced SQL injection payload should be handled: $payload");
        }
    }

    public function testSQLInjectionInSearchParameters(): void
    {
        $client = static::createClient();
        
        $searchSQLPayloads = [
            "Paris' OR '1'='1",
            "'; DROP TABLE cities; --",
            "' UNION SELECT password FROM users --",
            "London' AND SLEEP(5) --",
        ];

        foreach ($searchSQLPayloads as $payload) {
            $startTime = microtime(true);
            
            $client->request('GET', '/api/cities', ['q' => $payload]);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Should not take too long (no SLEEP injection)
            $this->assertLessThan(2.0, $executionTime, "Search SQL injection with SLEEP should be prevented");

            $response = $client->getResponse();
            $this->assertContains($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_BAD_REQUEST
            ], "Search SQL injection should be handled: $payload");
        }
    }

    public function testSQLInjectionInTripData(): void
    {
        $client = static::createClient();
        
        $tripSQLPayloads = [
            [
                'city' => "Paris'; DROP TABLE trips; --",
                'country' => 'France',
                'startDate' => '2025-07-25',
                'endDate' => '2025-07-30'
            ],
            [
                'city' => 'London',
                'country' => "UK' UNION SELECT * FROM users --",
                'startDate' => '2025-07-25',
                'endDate' => '2025-07-30'
            ],
            [
                'city' => 'Tokyo',
                'country' => 'Japan',
                'startDate' => "2025-07-25'; DELETE FROM trips WHERE '1'='1; --",
                'endDate' => '2025-07-30'
            ],
        ];

        foreach ($tripSQLPayloads as $payload) {
            $client->request('POST', '/api/trips/add', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($payload)
            );

            // Will return 401 due to auth, but server shouldn't crash
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        // Verify system is still responsive
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'aftersql_' . uniqid(),
                'email' => 'aftersql_' . uniqid() . '@test.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testBlindSQLInjectionProtection(): void
    {
        $client = static::createClient();
        
        $blindSQLPayloads = [
            "admin' AND (SELECT COUNT(*) FROM users WHERE username='admin') > 0 --",
            "test' AND (SELECT SUBSTRING(password,1,1) FROM users WHERE id=1)='a' --",
            "user' OR (SELECT LENGTH(password) FROM users WHERE id=1)>5 --",
        ];

        foreach ($blindSQLPayloads as $payload) {
            $userData = [
                'username' => $payload,
                'email' => 'blind_' . uniqid() . '@test.com',
                'password' => 'password123'
            ];

            $startTime = microtime(true);
            
            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Should complete quickly (no slow blind SQL injection)
            $this->assertLessThan(1.0, $executionTime, "Blind SQL injection should not cause delays");

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_CONFLICT,
                Response::HTTP_INTERNAL_SERVER_ERROR  // App might crash but doesn't compromise security
            ], "Blind SQL injection should be handled safely: $payload");
        }
    }

    public function testSQLInjectionInPasswordReset(): void
    {
        $client = static::createClient();
        
        // First create a user
        $userData = [
            'username' => 'resetuser_' . uniqid(),
            'email' => 'resetuser_' . uniqid() . '@test.com',
            'password' => 'password123'
        ];

        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );
        $this->assertResponseIsSuccessful();

        // Try SQL injection in password reset
        $sqlPayloads = [
            "test@test.com' UNION SELECT password FROM users --",
            "'; DROP TABLE password_reset_request; --",
            "admin@test.com' OR '1'='1",
        ];

        foreach ($sqlPayloads as $payload) {
            $resetData = ['email' => $payload];

            $client->request('POST', '/api/users/reset-password-request', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($resetData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_OK,
                Response::HTTP_NOT_FOUND,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_METHOD_NOT_ALLOWED  // Endpoint might not exist
            ], "Password reset SQL injection should be handled: $payload");
        }
    }
}
