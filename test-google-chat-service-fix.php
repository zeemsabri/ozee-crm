<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\GoogleChatService;
use Illuminate\Support\Facades\Log;

// Create a new instance of the application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Start the kernel to get access to the service container
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the GoogleChatService
try {
    echo "Testing GoogleChatService with null client...\n";

    // Create a new instance of GoogleChatService without passing a client
    $service = new GoogleChatService();

    // Use reflection to check if the client is null
    $reflection = new ReflectionClass($service);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $client = $clientProperty->getValue($service);

    echo "Client is " . ($client ? "not null" : "null") . "\n";

    echo "Test completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
