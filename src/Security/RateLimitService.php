<?php
/**
 * Rate Limit Service
 * 
 * This service provides rate limiting capabilities to protect against abuse,
 * brute force attacks, and denial-of-service attempts. It implements different
 * rate limiting strategies for various endpoints based on their sensitivity.
 * 
 * @package App\Security
 * @author Travel Project Team
 */

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
 * 
 * This service uses Symfony's rate limiter component to implement
 * different rate limiting strategies:
 * 1. Login attempts - limited per username to prevent credential stuffing
 * 2. API requests - general rate limiting for authenticated endpoints
 * 3. Protected API endpoints - stricter limits for sensitive operations
 */
class RateLimitService
{
    /**
     * Constructor that injects the three different rate limiter factories
     * 
     * @param RateLimiterFactory $loginLimiter Factory for creating login attempt limiters
     * @param RateLimiterFactory $apiLimiter Factory for creating general API request limiters
     * @param RateLimiterFactory $protectedApiLimiter Factory for creating strict API request limiters
     */
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $apiLimiter,
        private RateLimiterFactory $protectedApiLimiter
    ) {
    }

    /**
     * Check login rate limit (used before authentication attempt)
     * 
     * This method limits login attempts per username to prevent brute force
     * attacks targeting specific user accounts. The limits are defined in
     * the rate_limiter.yaml configuration file.
     *
     * @param string $username The username attempting to log in
     * @throws CustomUserMessageAuthenticationException When rate limit is exceeded
     * @return void
     */
    public function checkLoginRateLimit(string $username): void
    {
        // Create a limiter specific to this username
        // This ensures each username has its own rate limit counter
        $limiter = $this->loginLimiter->create($username);
        
        // Consume a token, throws a RateLimitExceededException if none are left
        if (false === $limiter->consume(1)->isAccepted()) {
            // Add a random delay to prevent timing attacks
            // This makes it harder for attackers to determine if a username exists
            usleep(random_int(100000, 500000)); // 0.1-0.5 seconds
            throw new CustomUserMessageAuthenticationException(
                'Too many login attempts. Please try again in a minute.'
            );
        }
    }

    /**
     * Check API rate limit by client IP
     * 
     * This method implements general rate limiting for most API endpoints.
     * It uses the client's IP address as the identifier to track request rates.
     * 
     * @param Request $request The current HTTP request
     * @throws TooManyRequestsHttpException When rate limit is exceeded
     * @return void
     */
    public function checkApiRateLimit(Request $request): void
    {
        // Use client IP as identifier to track requests per client
        $ip = $request->getClientIp();
        $limiter = $this->apiLimiter->create($ip);
        
        // If rate limit is exceeded, return a 429 Too Many Requests response
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'API rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Check protected API rate limit by client IP
     * 
     * This method implements stricter rate limiting for sensitive API endpoints
     * like registration and password reset. These endpoints need more protection
     * against abuse, so they have more restrictive limits.
     * 
     * @param Request $request The current HTTP request
     * @throws TooManyRequestsHttpException When rate limit is exceeded
     * @return void
     */
    public function checkProtectedApiRateLimit(Request $request): void
    {
        // Use client IP as identifier to track requests per client
        $ip = $request->getClientIp();
        $limiter = $this->protectedApiLimiter->create($ip);
        
        // If rate limit is exceeded, return a 429 Too Many Requests response
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'API rate limit exceeded. Please try again later.');
        }
    }
}
