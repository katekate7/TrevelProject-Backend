<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Rate limiter service for protecting against brute force attacks
 */
class RateLimitService
{
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $apiLimiter,
        private RateLimiterFactory $protectedApiLimiter
    ) {
    }

    /**
     * Check login rate limit (used before authentication attempt)
     */
    public function checkLoginRateLimit(string $username): void
    {
        // Create a limiter specific to this username
        $limiter = $this->loginLimiter->create($username);
        
        // Consume a token, throws a RateLimitExceededException if none are left
        if (false === $limiter->consume(1)->isAccepted()) {
            // Add a random delay to prevent timing attacks
            usleep(random_int(100000, 500000)); // 0.1-0.5 seconds
            throw new CustomUserMessageAuthenticationException(
                'Too many login attempts. Please try again in a minute.'
            );
        }
    }

    /**
     * Check API rate limit by client IP
     */
    public function checkApiRateLimit(Request $request): void
    {
        // Use client IP as identifier
        $ip = $request->getClientIp();
        $limiter = $this->apiLimiter->create($ip);
        
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'API rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Check protected API rate limit by client IP
     */
    public function checkProtectedApiRateLimit(Request $request): void
    {
        // Use client IP as identifier
        $ip = $request->getClientIp();
        $limiter = $this->protectedApiLimiter->create($ip);
        
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'API rate limit exceeded. Please try again later.');
        }
    }
}
