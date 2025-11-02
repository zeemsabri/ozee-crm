<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            if (! Schema::hasColumn('prompts', 'response_variables')) {
                $table->json('response_variables')->nullable()->after('template_variables');
            }
            if (! Schema::hasColumn('prompts', 'response_json_template')) {
                $table->json('response_json_template')->nullable()->after('response_variables');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            if (Schema::hasColumn('prompts', 'response_json_template')) {
                $table->dropColumn('response_json_template');
            }
            if (Schema::hasColumn('prompts', 'response_variables')) {
                $table->dropColumn('response_variables');
            }
        });
    }
};
