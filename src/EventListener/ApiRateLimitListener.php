<?php

namespace App\EventListener;

use App\Security\RateLimitService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that applies rate limiting to API requests
 */
class ApiRateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private RateLimitService $rateLimitService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        
        // Apply different rate limits based on path
        if (str_starts_with($path, '/api/login')) {
            // Login endpoint is handled by a specific authenticator
            return;
        } elseif (str_starts_with($path, '/api/users/register') || 
                 str_starts_with($path, '/api/users/forgot-password') || 
                 str_starts_with($path, '/api/users/reset-password-token/')) {
            // Public endpoints with specific rate limiting
            $this->rateLimitService->checkProtectedApiRateLimit($request);
        } elseif (str_starts_with($path, '/api')) {
            // All other API endpoints
            $this->rateLimitService->checkApiRateLimit($request);
        }
    }
}
