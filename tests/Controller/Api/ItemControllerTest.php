<?php
/**
 * Test suite for ItemController API endpoints.
 * 
 * This test class validates the ItemController's HTTP endpoints including:
 * - GET requests to item endpoints
 * - Response status validation
 * - Basic API functionality testing
 * 
 * Uses WebTestCase for integration testing of HTTP endpoints.
 * 
 * @package App\Tests\Controller
 * @author Travel Project Team
 */

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for ItemController API endpoints.
 * 
 * Tests HTTP endpoints to ensure proper API behavior
 * and response handling for item-related operations.
 */
final class ItemControllerTest extends WebTestCase
{
    /**
     * Test the item index endpoint accessibility.
     * 
     * Verifies that:
     * - GET request to /api/item endpoint works
     * - Response is successful (2xx status code)
     * - Basic endpoint functionality is working
     */
    public function testIndex(): void
    {
        // Arrange: Create test client
        $client = static::createClient();
        
        // Act: Make GET request to item endpoint
        $client->request('GET', '/api/item');

        // Assert: Verify successful response
        self::assertResponseIsSuccessful();
    }
}
