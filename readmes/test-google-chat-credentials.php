<?php

// This script tests the /api/user/google-chat/check-credentials endpoint
// to verify it returns the correct response

// First, let's make a request to the endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/user/google-chat/check-credentials');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'laravel_session='.getenv('TEST_SESSION_COOKIE'));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response: $response\n";

// Parse the JSON response
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'Error parsing JSON response: '.json_last_error_msg()."\n";
    exit(1);
}

// Check if the response contains the expected structure
if (! isset($data['has_credentials'])) {
    echo "Error: Response does not contain 'has_credentials' field\n";
    exit(1);
}

echo 'has_credentials: '.($data['has_credentials'] ? 'true' : 'false')."\n";

// Now let's check the implementation of the endpoint
echo "\nChecking implementation of GoogleChatUserController::checkGoogleCredentials...\n";

// Load the Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the controller and examine its implementation
$controller = app(\App\Http\Controllers\GoogleChatUserController::class);
$reflectionMethod = new ReflectionMethod($controller, 'checkGoogleCredentials');
$filename = $reflectionMethod->getFileName();
$startLine = $reflectionMethod->getStartLine();
$endLine = $reflectionMethod->getEndLine();

echo "Method defined in: $filename\n";
echo "Lines: $startLine-$endLine\n";

// Read the method implementation
$file = file($filename);
echo "Method implementation:\n";
for ($i = $startLine - 1; $i < $endLine; $i++) {
    echo $file[$i];
}

// Now let's check the User::hasGoogleCredentials method
echo "\nChecking implementation of User::hasGoogleCredentials...\n";

$user = \App\Models\User::first();
if (! $user) {
    echo "No users found in the database.\n";
    exit(1);
}

echo "Testing with user: {$user->name} (ID: {$user->id})\n";

// Check if user has Google credentials
$hasCredentials = $user->hasGoogleCredentials();
echo 'User has Google credentials: '.($hasCredentials ? 'Yes' : 'No')."\n";

// Get the Google account if it exists
$googleAccount = $user->googleAccount()->first();
if ($googleAccount) {
    echo "Google account found:\n";
    echo "- Email: {$googleAccount->email}\n";
    echo '- Created: '.date('Y-m-d H:i:s', $googleAccount->created)."\n";
    echo "- Expires in: {$googleAccount->expires_in} seconds\n";
    echo '- Is expired: '.($googleAccount->isExpired() ? 'Yes' : 'No')."\n";
} else {
    echo "No Google account found for this user.\n";
}

echo "\nTest completed.\n";
