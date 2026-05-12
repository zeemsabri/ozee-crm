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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('xero_contact_id')->nullable()->after('telegram_link_code');
            $table->string('xero_contact_name')->nullable()->after('xero_contact_id');
            $table->string('xero_contact_email')->nullable()->after('xero_contact_name');
            $table->string('xero_sync_mode', 20)->nullable()->after('xero_contact_email');
            $table->timestamp('xero_synced_at')->nullable()->after('xero_sync_mode');
            $table->foreignId('xero_synced_by_user_id')->nullable()->after('xero_synced_at')->constrained('users')->nullOnDelete();

            $table->index('xero_contact_id');
            $table->index('xero_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('xero_synced_by_user_id');
            $table->dropIndex(['xero_contact_id']);
            $table->dropIndex(['xero_synced_at']);
            $table->dropColumn([
                'xero_contact_id',
                'xero_contact_name',
                'xero_contact_email',
                'xero_sync_mode',
                'xero_synced_at',
            ]);
        });
    }
};