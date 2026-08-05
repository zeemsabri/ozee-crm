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
        Schema::table('bills', function (Blueprint $table) {
            $table->unsignedBigInteger('contractor_id')->nullable()->change();
            $table->unsignedBigInteger('project_expendable_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Note: Reversing this might fail if there are null values,
            // but we define the reverse operation.
            $table->unsignedBigInteger('contractor_id')->nullable(false)->change();
            $table->unsignedBigInteger('project_expendable_id')->nullable(false)->change();
        });
    }
};
