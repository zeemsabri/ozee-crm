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
        Schema::create('xero_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xero_connection_id')->constrained('xero_connections')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('tenant_name')->nullable();
            $table->string('tenant_type')->nullable();
            $table->string('auth_event_id')->nullable();
            $table->string('connection_id')->nullable();
            $table->timestamp('created_date_utc')->nullable();
            $table->timestamp('updated_date_utc')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();

            $table->unique(['xero_connection_id', 'tenant_id']);
            $table->index(['xero_connection_id', 'is_selected']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_tenants');
    }
};
