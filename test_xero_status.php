<?php
use App\Services\XeroBillService;
$xeroBillService = app()->make(XeroBillService::class);
try {
    $status = $xeroBillService->getInvoiceStatus('406feb01-fd67-40ef-8639-21b8bc962f63');
    print_r($status);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
