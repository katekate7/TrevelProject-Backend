<?php
/**
 * PHPUnit test bootstrap file for the Travel Project backend.
 * 
 * This file is executed before running any tests and handles:
 * - Autoloading of vendor dependencies
 * - Environment configuration loading
 * - Test-specific initialization
 * - Symfony framework bootstrap for testing
 * 
 * Ensures proper test environment setup for all test suites.
 * 
 * @package App\Tests
 * @author Travel Project Team
 */

use Symfony\Component\Dotenv\Dotenv;

// Load Composer autoloader for all vendor dependencies
require dirname(__DIR__).'/vendor/autoload.php';

// Bootstrap Symfony application for testing
if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    // Use project-specific bootstrap if available
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    // Fallback to loading environment variables directly
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
