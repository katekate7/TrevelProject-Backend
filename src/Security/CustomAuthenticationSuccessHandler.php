<?php
// src/Security/CustomAuthenticationSuccessHandler.php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private JWTTokenManagerInterface $jwtManager)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'message' => 'Login successful',
            'token' => $jwt  // Include token in response for frontend to use
        ]);

        $cookie = Cookie::create(
            'JWT',
            $jwt,
            time() + 3600,
            '/',
            null,            // Set back to null to work with same domain
            false,           // HTTPS -> false (for dev)
            false,           // HttpOnly -> false so frontend can access it
            false,
            'Lax'            // SameSite=Lax for cross-origin requests
        );

        $response->headers->setCookie($cookie);
        return $response;
    }
}
