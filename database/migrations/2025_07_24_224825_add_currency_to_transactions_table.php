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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency')->after('description')->default('aud');
            $table->boolean('is_paid')->after('amount')->default(false);
            $table->foreignIdFor(\App\Models\Transaction::class)->after('is_paid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->dropColumn('is_paid');
            $table->dropForeignIdFor(\App\Models\Transaction::class);
        });
    }
};
