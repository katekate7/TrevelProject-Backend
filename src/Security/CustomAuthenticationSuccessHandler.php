<?php
/**
 * Custom Authentication Success Handler
 * 
 * This handler is executed when a user successfully authenticates.
 * It generates a JWT token and delivers it both in the response body
 * and as a cookie, providing flexibility for frontend authentication.
 * 
 * @package App\Security
 * @author Travel Project Team
 */

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Handles successful authentication by generating and delivering JWT tokens
 * 
 * This handler creates a JWT token when a user successfully logs in and
 * delivers it both in the response body (for programmatic access) and
 * as a cookie (for enhanced security against XSS).
 */
class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * Constructor to inject the JWT token manager service
     * 
     * @param JWTTokenManagerInterface $jwtManager Service for creating and validating JWT tokens
     */
    public function __construct(private JWTTokenManagerInterface $jwtManager)
    {
    }

    /**
     * Handle successful authentication
     * 
     * This method is called when a user is successfully authenticated.
     * It generates a JWT token, includes it in the response body, and
     * sets it as a cookie for subsequent requests.
     *
     * @param Request $request The HTTP request that triggered authentication
     * @param TokenInterface $token The authentication token containing the user
     * @return JsonResponse Response with token in body and cookie
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        // Get the authenticated user and generate a JWT token
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        // Create a JSON response with success message and token
        $response = new JsonResponse([
            'message' => 'Login successful',
            'token' => $jwt  // Include token in response for frontend to use
        ]);

        // Create a cookie containing the JWT token
        $cookie = Cookie::create(
            'JWT',                // Cookie name
            $jwt,                 // Cookie value (the JWT token)
            time() + 3600,        // Expiration time (1 hour)
            '/',                  // Path (available throughout the site)
            null,                 // Domain (null = current domain only)
            false,                // Secure flag (false for dev, should be true in production)
            false,                // HttpOnly flag (false allows JS access, consider true for production)
            false,                // Raw flag
            'Lax'                 // SameSite policy (Lax allows some cross-origin requests)
        );

        // Add the cookie to the response headers
        $response->headers->setCookie($cookie);
        return $response;
    }
}
