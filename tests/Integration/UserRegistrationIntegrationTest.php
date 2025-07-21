<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserRegistrationIntegrationTest extends WebTestCase
{
    public function testUserCanRegisterWithValidData(): void
    {
        $client = static::createClient();
        
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'password123'
        ];

        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Registered', $response['message']);
    }

    public function testRegistrationFailsWithMissingData(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/users/register', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'testuser'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Missing fields', $response['error']);
    }
}
