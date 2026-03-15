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
        Schema::create('stripe_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('app_name');
            $table->string('app_id')->unique();
            $table->text('stripe_secret_key'); // Will be encrypted via model cast
            $table->string('stripe_public_key');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_configurations');
    }
};
