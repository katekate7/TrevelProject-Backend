<?php
/**
 * Front Controller for Symfony Application
 * 
 * This is the entry point for all web requests to your Symfony application.
 * It loads the Symfony kernel which handles all requests.
 */

// Import the App Kernel class that manages the application
use App\Kernel;

// Load Composer's autoloader to make all classes available
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Return a function that creates and returns the Symfony kernel
return function (array $context) {
    // Create a new Kernel with the current environment (dev/prod) and debug setting
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
