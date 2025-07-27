<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class FetchCurrencyRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
    public function handle(): void
    {
        $apiKey = config('services.exchange_rates.api_key');
        if (!$apiKey) {
            Log::error('EXCHANGE_RATES_API_KEY is not set in .env. Cannot fetch currency rates.');
            return;
        }

        $client = new Client();
        $baseUrl = 'https://api.exchangeratesapi.io/v1/latest';
        // For the free plan, the base currency is fixed to EUR.
        // We will fetch with EUR as base and then convert to USD rates.
        $apiBaseCurrency = 'EUR';

//        try {
            $response = $client->get($baseUrl, [
                'query' => [
                    'access_key' => $apiKey,
                    // 'base' => $apiBaseCurrency, // This parameter is ignored on the free plan
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['success']) && $data['success']) {
                $ratesFromApi = $data['rates'];
                $fetchedAt = Carbon::now();

                // Ensure USD rate is available to convert all other rates to USD base
                if (!isset($ratesFromApi['USD'])) {
                    Log::error('USD rate not found in API response. Cannot convert rates to USD base.');
                    return;
                }
                // This is the rate of 1 EUR in USD (e.g., if API says USD: 1.08, then 1 EUR = 1.08 USD)
                $eurToUsdRate = (float) $ratesFromApi['USD'];

                $ratesToStore = [];
                // The rate of USD relative to USD is always 1.0
                $ratesToStore['USD'] = 1.0;

                foreach ($ratesFromApi as $currencyCode => $rateFromEur) {
                    $rateFromEur = (float) $rateFromEur;

                    // Skip USD in this loop, as its rate to USD is already set to 1.0
                    if ($currencyCode === 'USD') {
                        continue;
                    }

                    // Calculate rate of 1 unit of $currencyCode in USD
                    // If 1 EUR = $eurToUsdRate USD, and 1 EUR = $rateFromEur $currencyCode
                    // Then 1 $currencyCode = ($eurToUsdRate / $rateFromEur) USD
                    $rateToUsd = $eurToUsdRate / $rateFromEur;
                    $ratesToStore[strtoupper($currencyCode)] = $rateToUsd;
                }

                // Explicitly set EUR's rate to USD, which is the $eurToUsdRate itself
                $ratesToStore['EUR'] = $eurToUsdRate;


                foreach ($ratesToStore as $currencyCode => $rate) {
                    CurrencyRate::updateOrCreate(
                        ['currency_code' => strtoupper($currencyCode)],
                        [
                            'rate_to_usd' => (float) $rate,
                            'fetched_at' => $fetchedAt,
                        ]
                    );
                }
                Log::info('Currency rates fetched and updated successfully (converted to USD base).');
            } else {
                Log::error('Failed to fetch currency rates from API.', ['api_response' => $data]);
            }
//        } catch (\Exception $e) {
//            Log::error('Error fetching currency rates: ' . $e->getMessage(), ['exception' => $e]);
//        }
    }
}
