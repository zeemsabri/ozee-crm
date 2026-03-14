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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('chat_name');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('phone');
            $table->foreignId('active_telegram_project_id')->nullable()->constrained('projects')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->bigInteger('telegram_group_id')->nullable()->after('google_chat_id')->index();
        });

        Schema::create('telegram_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->nullableMorphs('topicable'); // Creates topicable_id and topicable_type
            $table->bigInteger('telegram_thread_id')->nullable(); // The thread ID
            $table->string('name');
            $table->string('type')->default('general');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete()->after('user_id');
            $table->foreignId('telegram_topic_id')->nullable()->constrained('telegram_topics')->nullOnDelete()->after('project_id')->index();
            $table->bigInteger('telegram_message_id')->nullable()->after('telegram_topic_id')->index();
            $table->string('source')->default('crm')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['telegram_topic_id']);
            $table->dropColumn(['client_id', 'telegram_topic_id', 'telegram_message_id', 'source']);
        });

        Schema::dropIfExists('telegram_topics');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('telegram_group_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['active_telegram_project_id']);
            $table->dropColumn(['telegram_chat_id', 'active_telegram_project_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
