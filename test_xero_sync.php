<?php
use App\Models\Bill;
use App\Models\Transaction;
use App\Services\AirwallexService;
use App\Services\XeroBillService;
use App\Http\Controllers\Api\TransactionsController;
use Illuminate\Http\Request;

$bill = Bill::where('xero_invoice_id', '406feb01-fd67-40ef-8639-21b8bc962f63')->first();
if (!$bill) {
    echo "Bill not found\n";
    exit;
}
echo "Bill ID: " . $bill->id . "\n";

$transaction = $bill->transactions()->whereNull('xero_payment_id')->first();
if (!$transaction) {
    echo "No unsynced transaction found for this bill\n";
    exit;
}
echo "Transaction ID: " . $transaction->id . "\n";

// Let's call the controller's linkBill method
$controller = app()->make(TransactionsController::class);
$request = Request::create('/api/transactions/' . $transaction->id . '/link-bill', 'POST', [
    'bill_id' => $bill->id
]);
// We need to bind the route parameter since linkBill expects Transaction $transaction
$request->setRouteResolver(function () use ($transaction) {
    $route = new \Illuminate\Routing\Route('POST', 'foo', []);
    $route->setParameter('transaction', $transaction);
    return $route;
});

try {
    $response = $controller->linkBill($request, $transaction, app()->make(AirwallexService::class), app()->make(XeroBillService::class));
    echo "Response: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
