<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xero_payment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xero_connection_id')->constrained('xero_connections')->cascadeOnDelete();
            $table->string('payment_service_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->nullable();
            $table->string('provider')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['xero_connection_id', 'payment_service_id'], 'xps_conn_service_uidx');
            $table->index(['xero_connection_id', 'status'], 'xps_conn_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xero_payment_services');
    }
};
