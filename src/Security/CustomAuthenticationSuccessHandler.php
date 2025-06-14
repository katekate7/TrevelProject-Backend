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

        $response = new JsonResponse(['message' => 'Login successful']);

            $cookie = Cookie::create(
                'JWT',
                $jwt,
                time() + 3600,
                '/',
                null, // ❗️ЗАМІСТЬ null
                false,       // HTTPS -> false (бо dev)
                true,        // HttpOnly
                false,
                'Lax'        // ❗️Замість Strict
            );


        $response->headers->setCookie($cookie);
        return $response;
    }
}
