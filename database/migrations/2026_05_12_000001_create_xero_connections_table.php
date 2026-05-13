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
        Schema::create('xero_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('disconnected');
            $table->string('selected_tenant_id')->nullable();
            $table->string('selected_tenant_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('id_token')->nullable();
            $table->text('scope')->nullable();
            $table->string('token_type')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('selected_tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_connections');
    }
};
