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
        Schema::create('project_expendables', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name');
            $table->text('description')->nullable();

            // Ownership
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Financial fields
            $table->string('currency');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);

            // Status
            $table->string('status')->default('Pending Approval');

            // Polymorphic relationship to any model
            $table->morphs('expendable'); // creates expendable_id and expendable_type with index

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_expendables');
    }
};
