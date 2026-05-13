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
        Schema::table('users', function (Blueprint $table) {
            $table->string('xero_contact_id')->nullable()->after('telegram_link_code');
            $table->string('xero_contact_name')->nullable()->after('xero_contact_id');
            $table->string('xero_contact_email')->nullable()->after('xero_contact_name');
            $table->timestamp('xero_synced_at')->nullable()->after('xero_contact_email');

            $table->index('xero_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['xero_contact_id']);
            $table->dropColumn([
                'xero_contact_id',
                'xero_contact_name',
                'xero_contact_email',
                'xero_synced_at',
            ]);
        });
    }
};
