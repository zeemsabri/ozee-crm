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
        Schema::table('crm_services', function (Blueprint $table) {
            $table->decimal('default_amount', 15, 2)->nullable()->after('xero_item_code');
            $table->string('default_currency', 10)->nullable()->after('default_amount');
            $table->string('default_frequency', 20)->nullable()->after('default_currency');
            $table->json('default_payment_breakdown')->nullable()->after('default_frequency');
            $table->text('default_description')->nullable()->after('default_payment_breakdown');
            $table->string('default_xero_account_code', 50)->nullable()->after('default_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_services', function (Blueprint $table) {
            $table->dropColumn([
                'default_amount',
                'default_currency',
                'default_frequency',
                'default_payment_breakdown',
                'default_description',
                'default_xero_account_code',
            ]);
        });
    }
};
