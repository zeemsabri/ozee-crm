<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\CurrencyRate;
use Illuminate\Support\Collection; // Import the CurrencyRate model
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; // For caching database rates

trait HasFinancialCalculations
{
    /**
     * Cached currency conversion rates from the database.
     */
    protected ?array $cachedConversionRates = null;

    /**
     * Fetches and caches currency rates from the database.
     * Rates are stored as 'currency_code' => 'rate_to_usd'.
     */
    protected function getConversionRatesFromDatabase(): array
    {
        if ($this->cachedConversionRates === null) {
            // Cache rates for a reasonable period (e.g., 24 hours) to reduce DB hits
            // The cache key should be unique for currency rates to USD.
            $rates = Cache::remember('currency_rates_to_usd', 60 * 24, function () {
                $dbRates = CurrencyRate::all()->pluck('rate_to_usd', 'currency_code')->toArray();
                // Ensure USD is always 1.0 as a base, even if not explicitly in DB
                if (! isset($dbRates['USD'])) {
                    $dbRates['USD'] = 1.0;
                }

                return $dbRates;
            });
            $this->cachedConversionRates = $rates;
        }

        return $this->cachedConversionRates;
    }

    /**
     * Converts an amount from one currency to another using USD as a base.
     */
    protected function convertCurrency(float|string $amount, string $fromCurrency, string $toCurrency): float
    {
        $amount = (float) $amount;
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rates = $this->getConversionRatesFromDatabase(); // Get rates from DB/cache

        $fromRate = $rates[$fromCurrency] ?? null;
        $toRate = $rates[$toCurrency] ?? null;

        if (! $fromRate || ! $toRate) {
            Log::warning("Missing currency conversion rates for: {$fromCurrency} or {$toCurrency}. Returning original amount. Please ensure rates are fetched and stored.");

            return $amount; // Fallback to original amount if rates are missing
        }

        // Convert amount from its original currency to USD
        // If 'rate_to_usd' means "1 unit of currency = X USD", then to get amount in USD, multiply.
        // Example: 100 PKR * 0.0034 = 0.34 USD
        $amountInUSD = $amount * $fromRate;

        // Convert amount from USD to the target currency
        // To convert from USD to 'toCurrency', divide by 'toCurrency's rate_to_usd.
        // Example: 0.34 USD / 1.0 = 0.34 USD (if toCurrency is USD)
        // Example: 0.34 USD / 1.08 = 0.3148 EUR (if toCurrency is EUR)
        $convertedAmount = $amountInUSD / $toRate;

        return round($convertedAmount, 2);
    }

    /**
     * Static helper to process transactions for display.
     *
     * @return array Contains 'transactions' (converted) and 'stats'
     */
    public static function processTransactions(Collection|array $transactions, string $desiredCurrency): array
    {
        $instance = new self; // Create an instance to access non-static properties/methods

        return $instance->processTransactionsForDisplay($transactions, $desiredCurrency);
    }

    /**
     * Processes a collection of transactions, converting amounts to a desired currency
     * and calculating various financial statistics.
     *
     * @return array Contains 'transactions' (converted) and 'stats'
     */
    public function processTransactionsForDisplay(Collection|array $transactions, string $desiredCurrency): array
    {
        $transactions = collect($transactions); // Ensure it's a collection

        $totalIncome = 0.0;
        $totalExpense = 0.0;
        $totalPaid = 0.0;
        $totalBonus = 0.0; // Assuming 'bonus' is a transaction type if implemented

        $processedTransactions = $transactions->map(function ($transaction) use ($desiredCurrency) {
            $originalAmount = (float) $transaction['amount'];
            $originalCurrency = $transaction['currency'];

            $convertedAmount = $this->convertCurrency($originalAmount, $originalCurrency, $desiredCurrency);

            // Add the converted amount and desired currency to the transaction
            $transaction['converted_amount'] = $convertedAmount;
            $transaction['display_currency'] = $desiredCurrency;

            return $transaction;
        });

        // Calculate statistics based on converted amounts
        foreach ($processedTransactions as $transaction) {
            $amount = $transaction['converted_amount'];

            if ($transaction['type'] === 'income') {
                $totalIncome += $amount;
                if (isset($transaction['is_paid']) && $transaction['is_paid']) {
                    $totalPaid += $amount;
                }
            } elseif ($transaction['type'] === 'expense') {
                $totalExpense += $amount;
            } elseif ($transaction['type'] === 'bonus') { // If you have a 'bonus' type
                $totalBonus += $amount;
            }
        }

        $balance = $totalIncome - $totalExpense;
        $netProfitLoss = $totalIncome - $totalExpense; // Can be semantically different from balance

        $stats = [
            'totalIncome' => round($totalIncome, 2),
            'totalExpense' => round($totalExpense, 2),
            'totalPaid' => round($totalPaid, 2),
            'balance' => round($balance, 2),
            'totalBonus' => round($totalBonus, 2), // Will be 0 if 'bonus' type not used
            'netProfitLoss' => round($netProfitLoss, 2),
            'displayCurrency' => $desiredCurrency,
        ];

        return [
            'transactions' => $processedTransactions,
            'stats' => $stats,
        ];
    }
}
