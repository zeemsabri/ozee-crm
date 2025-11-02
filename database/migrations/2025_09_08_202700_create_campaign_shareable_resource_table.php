<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_shareable_resource', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignId('shareable_resource_id')->constrained('shareable_resources')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['campaign_id', 'shareable_resource_id'], 'campaign_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_shareable_resource');
    }
};
