<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presentable_id');
            $table->string('presentable_type');
            $table->string('title', 255);
            $table->string('type', 50)->index();
            $table->string('share_token', 64)->unique();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['presentable_id', 'presentable_type'], 'presentations_presentable_index');
        });

        Schema::create('presentation_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->string('meta_key', 100);
            $table->text('meta_value')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->string('template_name', 100);
            $table->string('title', 255)->nullable();
            $table->integer('display_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slide_id')->constrained('slides')->cascadeOnDelete();
            $table->string('block_type', 100);
            $table->json('content_data');
            $table->integer('display_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('slides');
        Schema::dropIfExists('presentation_metadata');
        Schema::dropIfExists('presentations');
    }
};
