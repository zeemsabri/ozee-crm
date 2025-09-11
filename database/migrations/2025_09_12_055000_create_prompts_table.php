<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->integer('version')->default(1);
            $table->text('system_prompt_text');
            $table->string('model_name')->default('gemini-2.5-flash-preview-05-20');
            $table->json('generation_config')->nullable();
            $table->json('template_variables')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
