<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->unique(); // e.g., USD, EUR, PKR
            $table->decimal('rate_to_usd', 15, 6); // Rate relative to USD (e.g., 1 EUR = 1.08 USD, so rate_to_usd for EUR is 1.08)
            $table->timestamp('fetched_at'); // When this rate was last fetched
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
