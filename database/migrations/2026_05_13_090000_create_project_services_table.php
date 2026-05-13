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
        Schema::create('project_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('enquiry_id', 100)->nullable();
            $table->string('service_id');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('frequency', 20)->default('one_off');
            $table->date('start_date')->nullable();
            $table->json('payment_breakdown')->nullable();
            $table->string('status', 50)->default('active');
            $table->string('service_tracking_type', 50)->default('operational_service');
            $table->boolean('show_on_leads_board')->default(false);
            $table->string('enquiry_status', 50)->nullable();
            $table->timestamp('enquiry_created_at')->nullable();
            $table->timestamp('enquiry_updated_at')->nullable();
            $table->json('enquiry_meta')->nullable();
            $table->string('xero_account_code', 50)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'enquiry_id']);
            $table->index('enquiry_id');
            $table->index(['project_id', 'service_tracking_type', 'show_on_leads_board'], 'project_services_leads_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_services');
    }
};
