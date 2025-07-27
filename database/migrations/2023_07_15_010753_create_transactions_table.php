<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('description', 255);
            $table->decimal('amount', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('hours_spent', 8, 2)->nullable();
            $table->enum('type', ['income', 'expense', 'bonus'])->default('expense');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
