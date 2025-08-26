<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'checklist')) {
                $table->json('checklist')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('users', 'notes')) {
                $table->json('notes')->nullable()->after('checklist');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('users', 'checklist')) {
                $table->dropColumn('checklist');
            }
        });
    }
};
