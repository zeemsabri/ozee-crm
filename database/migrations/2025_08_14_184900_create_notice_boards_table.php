<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('shareable_resources', function (Blueprint $table) {
            // First drop the existing type column
            $table->boolean('notice');
        });

    }

    public function down(): void
    {
        Schema::table('shareable_resources', function (Blueprint $table) {

            $table->dropColumn('notice');

        });
    }
};
