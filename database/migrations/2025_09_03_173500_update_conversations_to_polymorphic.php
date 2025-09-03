<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Add polymorphic columns (nullable to support existing rows and lead-only conversations)
            $table->string('conversable_type')->nullable()->after('subject');
            $table->unsignedBigInteger('conversable_id')->nullable()->after('conversable_type');
        });

        // Backfill conversable from existing client_id values
        DB::table('conversations')->whereNotNull('client_id')->update([
            'conversable_type' => 'App\\Models\\Client',
            'conversable_id' => DB::raw('client_id'),
        ]);

        // Make project_id nullable
        Schema::table('conversations', function (Blueprint $table) {
            // requires doctrine/dbal for change(); assume available in project
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        // Drop old client_id foreign key/column
        Schema::table('conversations', function (Blueprint $table) {
            // Safely drop foreign key if exists
            try {
                $table->dropForeign(['client_id']);
            } catch (\Throwable $e) {}
            if (Schema::hasColumn('conversations', 'client_id')) {
                $table->dropColumn('client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Re-add client_id and make project_id not nullable again
            $table->unsignedBigInteger('client_id')->nullable();
        });

        // Backfill client_id from conversable when it's a Client
        DB::table('conversations')
            ->where('conversable_type', 'App\\Models\\Client')
            ->whereNotNull('conversable_id')
            ->update([
                'client_id' => DB::raw('conversable_id'),
            ]);

        // Make project_id NOT NULL again (best effort; if data contains nulls this will fail)
        Schema::table('conversations', function (Blueprint $table) {
            try {
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
            } catch (\Throwable $e) {}
        });

        // Drop polymorphic columns
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'conversable_type')) {
                $table->dropColumn('conversable_type');
            }
            if (Schema::hasColumn('conversations', 'conversable_id')) {
                $table->dropColumn('conversable_id');
            }
        });
    }
};
