<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (!Schema::hasColumn('workflows', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_steps', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_steps', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('workflows', function (Blueprint $table) {
            if (Schema::hasColumn('workflows', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
