<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\AirwallexService::class);
try {
    $res = $service->getTransactions(['page_size' => 5, 'transaction_type' => 'PAYOUT']);
    echo "PAYOUT count: " . count($res['items']) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
