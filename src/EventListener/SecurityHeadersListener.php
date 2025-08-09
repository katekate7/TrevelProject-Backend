<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all HTTP responses
 */
class SecurityHeadersListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        
        // Prevent browsers from trying to guess the content type
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent embedding in iframes (clickjacking protection)
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Enable the XSS filter in browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Strict Transport Security (only enable in production)
        if ($_ENV['APP_ENV'] === 'prod') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Set referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), interest-cohort=()'
        );
    }
}
