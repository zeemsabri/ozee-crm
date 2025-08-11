<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('total_expendable_amount', 12, 2)->nullable()->after('total_amount');
            $table->string('currency', 10)->nullable()->after('total_expendable_amount');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('total_expendable_amount');
            $table->dropColumn('currency');
        });
    }
};
