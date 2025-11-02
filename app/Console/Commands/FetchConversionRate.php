<?php

namespace App\Console\Commands;

use App\Jobs\FetchCurrencyRatesJob;
use Illuminate\Console\Command;

class FetchConversionRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-conversion-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $new = new FetchCurrencyRatesJob;
        $new->handle();
    }
}
