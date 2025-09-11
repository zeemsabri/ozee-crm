<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('created_by_id')->constrained('campaigns')->nullOnDelete();
            }
            if (!Schema::hasColumn('leads', 'next_follow_up_date')) {
                $table->timestamp('next_follow_up_date')->nullable()->after('contacted_at')->index();
            }
            if (!Schema::hasColumn('leads', 'email_thread_history')) {
                $table->json('email_thread_history')->nullable()->after('metadata');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
            if (Schema::hasColumn('leads', 'next_follow_up_date')) {
                $table->dropColumn('next_follow_up_date');
            }
            if (Schema::hasColumn('leads', 'email_thread_history')) {
                $table->dropColumn('email_thread_history');
            }
        });
    }
};
