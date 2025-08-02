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
        Schema::create('placeholder_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->text('description')->nullable();
            $table->string('source_model', 255)->nullable()->comment('E.g., App\Models\Client');
            $table->string('source_attribute', 255)->nullable()->comment('E.g., name');
            $table->boolean('is_dynamic')->default(false)->comment('For placeholders generated at runtime (e.g., magic_link)');
            $table->boolean('is_repeatable')->default(false)->comment('Denotes a placeholder that represents a list of items'); // <-- New Column
            $table->boolean('is_link')->default(false)->comment('To generate link');
            $table->boolean('is_selectable')->default(false)->comment('To allow user to select from list');
            $table->timestamps();
        });

        // This replaces the old template_placeholders table
        Schema::create('email_template_placeholder', function (Blueprint $table) {
            $table->foreignId('email_template_id')->constrained('email_templates')->onDelete('cascade');
            $table->foreignId('placeholder_definition_id')->constrained('placeholder_definitions')->onDelete('cascade');
            $table->primary(['email_template_id', 'placeholder_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_placeholders');
    }
};
