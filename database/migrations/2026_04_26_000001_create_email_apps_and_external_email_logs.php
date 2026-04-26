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
        Schema::create('email_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('delivery_mode')->default('smtp');

            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_from_address')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->string('smtp_reply_to')->nullable();

            $table->string('api_provider')->nullable();
            $table->string('api_base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();

            $table->timestamps();
        });

        Schema::table('magic_links', function (Blueprint $table) {
            $table->foreignId('email_app_id')
                ->nullable()
                ->after('project_id')
                ->constrained('email_apps')
                ->nullOnDelete();
            $table->index('email_app_id');
        });

        Schema::create('email_app_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_app_id')->constrained('email_apps')->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['email_app_id', 'email_template_id']);
        });

        Schema::create('external_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('magic_link_id')->nullable()->constrained('magic_links')->nullOnDelete();
            $table->foreignId('email_app_id')->nullable()->constrained('email_apps')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('status');
            $table->string('provider')->nullable();
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_email_logs');
        Schema::dropIfExists('email_app_template');

        Schema::table('magic_links', function (Blueprint $table) {
            $table->dropIndex(['email_app_id']);
            $table->dropForeign(['email_app_id']);
            $table->dropColumn('email_app_id');
        });

        Schema::dropIfExists('email_apps');
    }
};
