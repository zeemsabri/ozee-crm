<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Exception;

class CurrencyConversionService
{
    /**
     * An array to cache currency rates to avoid multiple database calls.
     */
    protected array $rates = [];

    /**
     * Fetches all currency rates from the database.
     */
    protected function fetchRates(): void
    {
        if (empty($this->rates)) {
            $currencyRates = CurrencyRate::all();
            foreach ($currencyRates as $rate) {
                $this->rates[strtoupper($rate->currency_code)] = (float) $rate->rate_to_usd;
            }
        }
    }

    /**
     * Converts an amount from a source currency to a target currency.
     *
     * @param  float  $amount  The amount to convert.
     * @param  string  $fromCurrency  The source currency code (e.g., 'PKR', 'AUD').
     * @param  string  $toCurrency  The target currency code (e.g., 'PKR', 'AUD').
     * @return float The converted amount.
     *
     * @throws Exception If a currency rate is not found.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $this->fetchRates();

        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        if (! isset($this->rates[$fromCurrency])) {
            throw new Exception("Missing conversion rate for: {$fromCurrency}.");
        }

        if (! isset($this->rates[$toCurrency])) {
            throw new Exception("Missing conversion rate for: {$toCurrency}.");
        }

        // Convert amount to USD first
        $amountInUsd = $amount * $this->rates[$fromCurrency];

        // Then convert from USD to the target currency
        return $amountInUsd / $this->rates[$toCurrency];
    }
}
