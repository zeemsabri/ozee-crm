<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('social_media_link')->nullable();
            $table->text('preferred_keywords')->nullable();
            $table->string('google_chat_id', 255)->nullable();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'completed', 'on_hold', 'archived'])->default('active');
            $table->string('project_type', 255)->nullable();
            $table->json('services')->nullable();
            $table->json('service_details')->nullable();
            $table->string('source', 255)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->text('contract_details')->nullable();
            $table->string('google_drive_link')->nullable();
            $table->enum('payment_type', ['one_off', 'monthly'])->default('one_off');
            $table->string('logo')->nullable();
            $table->json('documents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
