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
        Schema::create('stripe_subscriptions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('app_id')->index();
            $blueprint->string('stripe_subscription_id')->unique();
            $blueprint->string('stripe_customer_id')->nullable();
            $blueprint->string('status');
            $blueprint->integer('amount_total')->nullable(); // Total to be collected if it's an installment
            $blueprint->string('currency')->default('aud');
            $blueprint->timestamp('cancel_at')->nullable();
            $blueprint->timestamp('canceled_at')->nullable();
            $blueprint->timestamp('ended_at')->nullable();
            $blueprint->json('metadata')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_subscriptions');
    }
};
