<?php
/**
 * Controller for handling root URL redirections.
 * 
 * This controller manages the redirection from the root path (/) 
 * to the appropriate login endpoint, ensuring users are directed
 * to the correct authentication flow.
 * 
 * @package App\Controller
 * @author Travel Project Team
 */

// src/Controller/RedirectController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simple controller to handle root path redirection.
 * 
 * This controller provides a single endpoint that redirects users
 * from the application root to the API login endpoint.
 */
class RedirectController
{
    /**
     * Redirect root path to login endpoint.
     * 
     * When users visit the root URL (/), this method automatically
     * redirects them to the API login page (/api/login).
     * This ensures a smooth user experience by directing users
     * to the appropriate starting point of the application.
     *
     * @return RedirectResponse - HTTP redirect response to login page
     */
    #[Route('/', name: 'redirect_to_login', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        // Redirect to the API login endpoint
        return new RedirectResponse('/api/login');
    }
}
