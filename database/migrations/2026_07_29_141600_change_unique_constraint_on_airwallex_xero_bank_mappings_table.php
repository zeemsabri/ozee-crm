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
        Schema::table('airwallex_xero_bank_mappings', function (Blueprint $table) {
            $table->unique(['xero_connection_id', 'xero_account_id'], 'awx_xero_mapping_account_unique');
            $table->dropUnique('awx_xero_mapping_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airwallex_xero_bank_mappings', function (Blueprint $table) {
            $table->dropUnique('awx_xero_mapping_account_unique');
            $table->unique(['xero_connection_id', 'airwallex_currency'], 'awx_xero_mapping_unique');
        });
    }
};
