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
        Schema::table('shareable_resources', function (Blueprint $table) {
            if (!Schema::hasColumn('shareable_resources', 'sent_push')) {
                $table->boolean('sent_push')->default(false)->after('visible_to_client');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shareable_resources', function (Blueprint $table) {
            if (Schema::hasColumn('shareable_resources', 'sent_push')) {
                $table->dropColumn('sent_push');
            }
        });
    }
};
