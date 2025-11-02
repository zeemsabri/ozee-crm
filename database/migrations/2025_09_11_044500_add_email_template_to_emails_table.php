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
        if (Schema::hasTable('emails') && ! Schema::hasColumn('emails', 'email_template')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->string('email_template')->nullable()->after('template_data');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('emails') && Schema::hasColumn('emails', 'email_template')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->dropColumn('email_template');
            });
        }
    }
};
