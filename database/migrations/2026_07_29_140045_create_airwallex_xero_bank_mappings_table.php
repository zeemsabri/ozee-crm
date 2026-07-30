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
        Schema::create('airwallex_xero_bank_mappings', function (Blueprint $table) {
            $table->id();
            
            // Links this mapping to the specific xero connection in the app
            $table->foreignId('xero_connection_id')->constrained()->cascadeOnDelete();
            
            // The currency of the Airwallex wallet (e.g., 'AUD', 'USD')
            $table->string('airwallex_currency', 3);
            
            // The Xero Account data
            $table->uuid('xero_account_id');
            $table->string('xero_account_name', 255);
            $table->string('xero_currency_code', 3);
            
            $table->timestamps();
            
            // Ensure a single Airwallex currency wallet maps to only one Xero account per connection
            $table->unique(['xero_connection_id', 'airwallex_currency'], 'awx_xero_mapping_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airwallex_xero_bank_mappings');
    }
};
