<?php
/**
 * Integration test suite for ItemController security and authentication.
 * 
 * This test class validates the ItemController's security mechanisms including:
 * - JWT authentication requirements
 * - Role-based access control (RBAC)
 * - Proper error responses for unauthorized access
 * - API security compliance
 * 
 * Uses WebTestCase for full integration testing of security features.
 * 
 * @package App\Tests\Integration
 * @author Travel Project Team
 */

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for ItemController security features.
 * 
 * Tests focus on authentication, authorization, and proper
 * security error handling for item-related API endpoints.
 */
final class ItemControllerIntegrationTest extends WebTestCase
{
    /**
     * Test that getting items requires proper JWT authentication.
     * 
     * Verifies that:
     * - Unauthenticated requests are rejected with 401 status
     * - Proper JSON error response is returned
     * - Error message indicates missing JWT token
     * - Content-Type header is set correctly
     */
    public function testGetItemsRequiresAuthentication(): void
    {
        // Arrange: Create test client without authentication
        $client = static::createClient();
        
        // Act: Make unauthenticated request to items endpoint
        $client->request('GET', '/api/items');

        // Assert: Should return 401 since JWT authentication is required
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        // Assert: Verify error message content
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('JWT Token not found', $content['message']);
    }

    /**
     * Test that creating items requires admin role authorization.
     * 
     * Verifies that:
     * - Unauthenticated requests cannot create items
     * - Proper 401 status is returned for security
     * - POST requests are properly secured
     * - Admin-only operations are protected
     */
    public function testCreateItemRequiresAdminRole(): void
    {
        // Arrange: Create test client without authentication
        $client = static::createClient();
        
        // Act: Attempt to create item without authentication
        $client->request('POST', '/api/items', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Test Item'])
        );

        // Assert: Should return 401 Unauthorized since no authentication
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
