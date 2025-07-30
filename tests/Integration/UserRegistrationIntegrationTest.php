<?php
/**
 * Integration test suite for user registration functionality.
 * 
 * This test class validates the complete user registration workflow including:
 * - Successful registration with valid data
 * - Registration failure scenarios with invalid/missing data
 * - API response validation
 * - Error handling and appropriate HTTP status codes
 * 
 * Uses WebTestCase for full HTTP integration testing.
 * 
 * @package App\Tests\Integration
 * @author Travel Project Team
 */

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for user registration endpoints.
 * 
 * Tests the complete registration flow from HTTP request
 * to database persistence and response generation.
 */
final class UserRegistrationIntegrationTest extends WebTestCase
{
    /**
     * Test successful user registration with valid data.
     * 
     * Verifies that:
     * - Valid user data is accepted and processed
     * - HTTP 201 Created status is returned
     * - Success message is included in response
     * - Registration endpoint works end-to-end
     */
    public function testUserCanRegisterWithValidData(): void
    {
        // Arrange: Create test client and valid user data
        $client = static::createClient();
        
        // Use unique identifiers to avoid conflicts in test database
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'StrongPass123!'
        ];

        // Act: Send registration request
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );

        // Assert: Verify successful registration
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Registered', $response['message']);
    }

    /**
     * Test registration failure with incomplete data.
     * 
     * Verifies that:
     * - Missing required fields are detected and rejected
     * - HTTP 400 Bad Request status is returned
     * - Appropriate error message is provided
     * - Input validation works correctly
     */
    public function testRegistrationFailsWithMissingData(): void
    {
        // Arrange: Create test client and incomplete user data
        $client = static::createClient();
        
        // Act: Send registration request with missing required fields
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'testuser']) // Missing email and password
        );

        // Assert: Verify registration failure with proper error response
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Missing fields', $response['error']);
    }
}
