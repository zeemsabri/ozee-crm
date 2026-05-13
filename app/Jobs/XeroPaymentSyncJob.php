<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Enums\BillStatus;
use App\Services\XeroBillService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class XeroPaymentSyncJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(XeroBillService $xeroBillService): void
    {
        $bills = Bill::where('status', BillStatus::Approved)
            ->whereNotNull('xero_invoice_id')
            ->get();

        foreach ($bills as $bill) {
            try {
                $xeroData = $xeroBillService->getInvoiceStatus($bill->xero_invoice_id);

                if ($xeroData['status'] === 'PAID' || $xeroData['amount_due'] <= 0) {
                    $this->markAsPaid($bill);
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync payment for bill {$bill->id}: " . $e->getMessage());
            }
        }
    }

    private function markAsPaid(Bill $bill): void
    {
        $bill->status = BillStatus::Paid;
        $bill->save();

        // Update linked transactions
        $bill->transactions()->update([
            'is_paid' => true,
            'payment_date' => now(),
        ]);

        Log::info("Bill {$bill->id} marked as Paid via Xero sync.");
    }
}
