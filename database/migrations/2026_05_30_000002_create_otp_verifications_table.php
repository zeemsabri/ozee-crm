<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('otp_hash');
            $table->string('project_token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('session_token', 64)->nullable()->unique();
            $table->unsignedBigInteger('guest_user_id')->nullable();
            $table->timestamps();

            $table->index(['email', 'project_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
