<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorizables', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('categorizable_id');
            $table->string('categorizable_type');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->index(['categorizable_id', 'categorizable_type'], 'categorizables_cid_ctype_index');
            $table->unique(['category_id', 'categorizable_id', 'categorizable_type'], 'categorizables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorizables');
    }
};
