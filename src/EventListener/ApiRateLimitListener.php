<?php
/**
 * API Rate Limit Listener
 * 
 * This listener intercepts incoming HTTP requests and applies rate limiting
 * to protect the API from abuse, brute force attacks, and to ensure
 * fair usage of resources.
 *
 * @package App\EventListener
 * @author Travel Project Team
 */

namespace App\EventListener;

use App\Security\RateLimitService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that applies rate limiting to API requests
 * 
 * This class subscribes to kernel request events and checks if incoming
 * requests should be rate-limited based on their path and client IP.
 * Different endpoints may have different rate limit policies.
 */
class ApiRateLimitListener implements EventSubscriberInterface
{
    /**
     * Constructor to inject the rate limit service
     * 
     * @param RateLimitService $rateLimitService Service that handles the rate limiting logic
     */
    public function __construct(
        private RateLimitService $rateLimitService
    ) {
    }

    /**
     * Define which events this subscriber listens to
     * 
     * This method registers the listener for the kernel.request event
     * with a priority of 20, allowing it to run early in the request cycle,
     * but after critical components like firewall.
     *
     * @return array Events and their corresponding handler methods
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    /**
     * Handle incoming requests and apply rate limiting
     * 
     * This method is called for every request and applies appropriate
     * rate limiting rules based on the URL path. Different API endpoints
     * may have different rate limits to balance security and usability.
     *
     * @param RequestEvent $event The kernel request event
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip sub-requests (like ESI or forwarded requests)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        
        // Apply different rate limits based on path
        if (str_starts_with($path, '/api/login')) {
            // Login endpoint is handled by a specific authenticator
            // which has its own rate limiting mechanism
            return;
        } elseif (str_starts_with($path, '/api/users/register') || 
                 str_starts_with($path, '/api/users/forgot-password') || 
                 str_starts_with($path, '/api/users/reset-password-token/')) {
            // Public endpoints with stricter rate limiting
            // These are security-sensitive operations that need protection
            $this->rateLimitService->checkProtectedApiRateLimit($request);
        } elseif (str_starts_with($path, '/api')) {
            // All other API endpoints get standard rate limiting
            // This prevents abuse while allowing normal API usage
            $this->rateLimitService->checkApiRateLimit($request);
        }
    }
}
