<?php

/**
 * This script tests the root route to verify that it correctly serves the client dashboard view
 * when a token parameter is present in the URL.
 *
 * To run this test:
 * 1. Run this script with: php test-client-dashboard-route.php
 */

// Bootstrap the Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Set up logging
Log::info('Starting Client Dashboard Route test');

try {
    // Create a test token
    $token = 'test_token_'.time();

    echo "Testing client dashboard route with token: {$token}\n";

    // Create a request with the token parameter
    $request = Request::create('/client/dashboard', 'GET', ['token' => $token]);

    // Debug the request
    echo 'Request URL: '.$request->url()."\n";
    echo 'Request query parameters: '.json_encode($request->query())."\n";
    echo 'Request has token: '.($request->has('token') ? 'Yes' : 'No')."\n";
    echo 'Request token value: '.$request->query('token')."\n";

    // Get the route collection
    $routes = Route::getRoutes();

    // Find the client dashboard route
    $route = $routes->match($request);

    // Debug the route
    echo 'Route name: '.($route->getName() ?? 'unnamed')."\n";
    echo 'Route action: '.($route->getActionName() ?? 'unknown')."\n";

    // Execute the route
    $response = $route->run($request);

    // Debug the response
    echo 'Response class: '.get_class($response)."\n";

    // Check if the response is a view or a redirect
    if (method_exists($response, 'getName')) {
        $viewName = $response->getName();
        echo "Response is a view: {$viewName}\n";

        // Check if the view is the client dashboard
        if ($viewName === 'client_dashboard') {
            echo "Success! The client dashboard route correctly serves the client dashboard view when a token is present.\n";

            // Check if the token is passed to the view
            $data = $response->getData();
            if (isset($data['token']) && $data['token'] === $token) {
                echo "Success! The token is correctly passed to the view.\n";
            } else {
                echo "Error: The token is not correctly passed to the view.\n";
            }
        } else {
            echo "Error: The client dashboard route does not serve the client dashboard view when a token is present.\n";
        }
    } elseif ($response instanceof \Illuminate\Http\RedirectResponse) {
        echo 'Response is a redirect to: '.$response->getTargetUrl()."\n";

        // If we're testing with a token, we shouldn't get a redirect
        if ($token) {
            echo "Error: The client dashboard route is redirecting when a token is present.\n";
            echo "This suggests that the controller is not correctly detecting the token.\n";

            // Let's try to debug by directly calling the controller method
            $controller = new \App\Http\Controllers\ClientDashboardController;
            $directResponse = $controller->index($request);

            echo 'Direct controller call response class: '.get_class($directResponse)."\n";

            if (method_exists($directResponse, 'getName')) {
                echo 'Direct controller call response is a view: '.$directResponse->getName()."\n";
            } elseif ($directResponse instanceof \Illuminate\Http\RedirectResponse) {
                echo 'Direct controller call response is a redirect to: '.$directResponse->getTargetUrl()."\n";
            }
        } else {
            echo "Success! The client dashboard route correctly redirects to the home page when no token is present.\n";
        }
    } else {
        echo "Error: The response is neither a view nor a redirect.\n";
        echo 'Response type: '.get_class($response)."\n";
    }

    echo "\nTesting root route without token\n";

    // Create a request without the token parameter
    $request = Request::create('/', 'GET');

    // Find the root route
    $route = $routes->match($request);

    // Execute the route
    $response = $route->run($request);

    // Check if the response is an Inertia response
    if (get_class($response) === 'Inertia\Response') {
        echo "Success! The root route correctly serves the Welcome page when no token is present.\n";
    } else {
        echo "Error: The root route does not serve the Welcome page when no token is present.\n";
        echo 'Response type: '.get_class($response)."\n";
    }

    echo "\nTest completed successfully!\n";

} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    Log::error('Error in Client Dashboard Route test: '.$e->getMessage(), [
        'error' => $e->getTraceAsString(),
    ]);
}

Log::info('Completed Client Dashboard Route test');
