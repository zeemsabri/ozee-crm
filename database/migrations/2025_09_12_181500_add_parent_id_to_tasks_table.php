<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Self-referencing parent task
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop foreign key then column (Laravel will infer the FK name from convention)
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
