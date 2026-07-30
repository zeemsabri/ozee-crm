<?php
use App\Services\AirwallexService;
$tx_id = '5442e9cf-39be-4732-8184-78d0e0a978c7'; // the bank_transaction_id from the log
$airwallexService = app()->make(AirwallexService::class);
try {
    $data = $airwallexService->getTransaction($tx_id);
    print_r($data);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
