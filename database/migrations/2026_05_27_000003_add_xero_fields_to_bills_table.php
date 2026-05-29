<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('xero_account_code', 50)->nullable()->after('transaction_type_id');
            $table->string('xero_tax_type', 50)->nullable()->after('xero_account_code');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['xero_account_code', 'xero_tax_type']);
        });
    }
};
