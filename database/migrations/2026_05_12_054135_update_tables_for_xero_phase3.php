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
        Schema::table('transaction_types', function (Blueprint $table) {
            $table->string('xero_account_code')->nullable()->after('slug');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('draft');
            $table->string('xero_invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('users');
            $table->foreignId('project_id')->constrained();
            $table->foreignId('project_expendable_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending_approval');
            $table->string('xero_invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->constrained()->after('transaction_type_id');
            $table->string('xero_payment_id')->nullable()->after('bill_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
            $table->dropColumn(['bill_id', 'xero_payment_id']);
        });

        Schema::dropIfExists('bills');
        Schema::dropIfExists('invoices');

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->dropColumn('xero_account_code');
        });
    }
};
