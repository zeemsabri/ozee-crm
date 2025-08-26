<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Get the MagicLinkController
$controller = $app->make(\App\Http\Controllers\Api\MagicLinkController::class);

// Create a test request with an email
$request = new \Illuminate\Http\Request();
$request->merge(['email' => 'info@mmsitandwebsolutions.com.au']);

// Call the sendClientMagicLink method
try {
    $response = $controller->sendClientMagicLink($request);
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response content: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
