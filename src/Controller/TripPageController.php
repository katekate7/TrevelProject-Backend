<?php
/**
 * Controller for handling trip-related page rendering.
 * 
 * This controller manages the presentation layer for trip-related
 * functionality, rendering Twig templates for trip pages.
 * 
 * @package App\Controller
 * @author Travel Project Team
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller responsible for trip page rendering.
 * 
 * This controller extends AbstractController to provide access to
 * common Symfony services like templating and handles the rendering
 * of trip-related pages using Twig templates.
 */
class TripPageController extends AbstractController
{
    /**
     * Render the main trip page.
     * 
     * This method handles GET requests to /trip and renders the
     * trip index template. This serves as the main entry point
     * for trip-related functionality in the web interface.
     *
     * @return Response - Rendered HTML response with trip page content
     */
    #[Route('/trip', name: 'app_trip_page')]
    public function index(): Response
    {
        // Render the trip index template using Twig
        return $this->render('trip/index.html.twig');
    }
}
