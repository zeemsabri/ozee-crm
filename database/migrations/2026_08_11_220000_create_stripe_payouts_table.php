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
        Schema::create('stripe_payouts', function (Blueprint $table) {
            $table->string('id')->primary(); // Stripe Payout ID (po_...)
            $table->string('trace_id')->nullable()->index();
            $table->string('statement_descriptor')->nullable()->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('AUD');
            $table->string('status', 50)->default('PAID');
            $table->date('arrival_date')->nullable();
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_fees', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_payouts');
    }
};
