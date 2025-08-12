<?php
/**
 * Security Headers Listener
 * 
 * This listener automatically adds industry-standard security headers to all HTTP responses
 * sent by the application. These headers help protect against common web vulnerabilities
 * such as XSS, clickjacking, MIME-type confusion, and information disclosure.
 * 
 * @package App\EventListener
 * @author Travel Project Team
 */

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all HTTP responses
 * 
 * This event subscriber implements OWASP recommended security headers to enhance
 * the application's defense against common web vulnerabilities. It applies
 * these headers to every outgoing HTTP response.
 */
class SecurityHeadersListener implements EventSubscriberInterface
{
    /**
     * Register this listener for the kernel.response event
     * 
     * This method specifies that the onKernelResponse method should be called
     * during the response event with a default priority (0).
     * 
     * @return array Events and their corresponding handler methods
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Add security headers to the HTTP response
     * 
     * This method is called before each response is sent to the client.
     * It adds various security headers that help protect against common
     * web vulnerabilities and follows security best practices.
     * 
     * @param ResponseEvent $event The kernel response event
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        // Skip sub-requests (like ESI or forwarded requests)
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        
        // Prevent browsers from trying to guess the content type
        // This helps prevent MIME-type confusion attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent embedding in iframes (clickjacking protection)
        // This stops attackers from invisibly embedding your site
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Enable the XSS filter in browsers
        // This activates built-in XSS protections in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Strict Transport Security (only enable in production)
        // Forces browsers to use HTTPS for future visits
        if ($_ENV['APP_ENV'] === 'prod') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Set referrer policy
        // Controls how much referrer information is included with requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy (formerly Feature Policy)
        // Restricts which browser features the site can use
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), interest-cohort=()'
        );
    }
}
