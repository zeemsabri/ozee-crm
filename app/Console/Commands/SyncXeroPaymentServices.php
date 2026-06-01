<?php

namespace App\Console\Commands;

use App\Services\XeroPaymentServiceCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncXeroPaymentServices extends Command
{
    protected $signature = 'xero:refresh-payment-services';

    protected $description = 'Refresh cached payment services from Xero for the active tenant.';

    public function __construct(private readonly XeroPaymentServiceCatalog $paymentServiceCatalog)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $services = $this->paymentServiceCatalog->syncCache();
            $this->info('Synced Xero payment services: '.count($services));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (Str::contains($e->getMessage(), 'PaymentServices API is unavailable for this app', true)) {
                $this->warn($e->getMessage());

                return self::SUCCESS;
            }

            $this->error('Failed to sync Xero payment services: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
