<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // No-op: presentations already support polymorphic presentable (Lead/Client)
    }

    public function down(): void
    {
        // No-op
    }
};
