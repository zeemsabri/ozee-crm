<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\XeroInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncXeroInvoices extends Command
{
    protected $signature = 'xero:sync-invoices';

    protected $description = 'Sync invoice statuses and payments from Xero for all pending/authorised invoices.';

    public function __construct(private readonly XeroInvoiceService $xeroInvoiceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $invoices = Invoice::whereNotIn('status', ['paid', 'voided', 'rejected'])
            ->whereNotNull('xero_invoice_id')
            ->get();

        $this->info('Found ' . $invoices->count() . ' invoices to sync.');

        foreach ($invoices as $invoice) {
            try {
                $originalStatus = $invoice->status;
                $syncedInvoice = $this->xeroInvoiceService->syncLocalInvoiceFromXero($invoice);

                if ($originalStatus !== $syncedInvoice->status) {
                    $this->info("Invoice {$invoice->id} status updated from {$originalStatus} to {$syncedInvoice->status}.");
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync invoice {$invoice->id}: " . $e->getMessage());
                Log::error("Failed to sync invoice {$invoice->id} from Xero: " . $e->getMessage());
            }
        }

        $this->info('Invoice sync completed.');

        return self::SUCCESS;
    }
}
