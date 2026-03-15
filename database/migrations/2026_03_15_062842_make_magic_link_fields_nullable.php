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
        Schema::table('magic_links', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->foreignId('project_id')->nullable()->change();
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->foreignId('project_id')->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
