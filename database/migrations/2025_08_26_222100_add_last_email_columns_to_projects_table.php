<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'last_email_sent')) {
                $table->timestamp('last_email_sent')->nullable()->after('documents');
            }
            if (!Schema::hasColumn('projects', 'last_email_received')) {
                $table->timestamp('last_email_received')->nullable()->after('last_email_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'last_email_received')) {
                $table->dropColumn('last_email_received');
            }
            if (Schema::hasColumn('projects', 'last_email_sent')) {
                $table->dropColumn('last_email_sent');
            }
        });
    }
};
