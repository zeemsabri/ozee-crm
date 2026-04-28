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
        Schema::table('client_vault_credentials', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained('projects')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('client')->after('created_by');
            $table->boolean('is_visible_to_client')->default(true)->after('source');
            $table->text('encrypted_pin')->nullable()->after('encrypted_password');
            $table->softDeletes();

            $table->index(['project_id', 'source']);
            $table->index(['client_id', 'source']);
            $table->index(['created_by', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_vault_credentials', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'source']);
            $table->dropIndex(['client_id', 'source']);
            $table->dropIndex(['created_by', 'source']);

            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source', 'is_visible_to_client', 'encrypted_pin', 'deleted_at']);
        });
    }
};
