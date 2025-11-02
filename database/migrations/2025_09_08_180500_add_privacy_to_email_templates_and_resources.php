<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Email templates: add is_private
        if (Schema::hasTable('email_templates') && ! Schema::hasColumn('email_templates', 'is_private')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->boolean('is_private')->default(false)->after('is_default');
            });
        }

        // Shareable resources: add visible_to_team and is_private
        if (Schema::hasTable('shareable_resources')) {
            Schema::table('shareable_resources', function (Blueprint $table) {
                if (! Schema::hasColumn('shareable_resources', 'visible_to_team')) {
                    $table->boolean('visible_to_team')->default(false)->after('visible_to_client');
                }
                if (! Schema::hasColumn('shareable_resources', 'is_private')) {
                    $table->boolean('is_private')->default(false)->after('visible_to_team');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates') && Schema::hasColumn('email_templates', 'is_private')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->dropColumn('is_private');
            });
        }
        if (Schema::hasTable('shareable_resources')) {
            Schema::table('shareable_resources', function (Blueprint $table) {
                if (Schema::hasColumn('shareable_resources', 'visible_to_team')) {
                    $table->dropColumn('visible_to_team');
                }
                if (Schema::hasColumn('shareable_resources', 'is_private')) {
                    $table->dropColumn('is_private');
                }
            });
        }
    }
};
