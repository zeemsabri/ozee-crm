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
        Schema::create('stripe_subscription_payments', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('stripe_subscription_id')->index();
            $blueprint->string('stripe_invoice_id')->unique();
            $blueprint->integer('amount');
            $blueprint->string('currency')->default('aud');
            $blueprint->string('status');
            $blueprint->timestamp('paid_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_subscription_payments');
    }
};
