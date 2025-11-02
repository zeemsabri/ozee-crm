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
        Schema::table('project_notes', function (Blueprint $table) {
            // Add polymorphic columns for creator
            $table->unsignedBigInteger('creator_id')->nullable()->after('user_id');
            $table->string('creator_type')->nullable()->after('creator_id');

            // Make user_id nullable for backward compatibility
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Add index for better query performance
            $table->index(['creator_id', 'creator_type']);
        });

        $notes = \Illuminate\Support\Facades\DB::table('project_notes')->get();
        foreach ($notes as $note) {
            \Illuminate\Support\Facades\DB::table('project_notes')->where('id', $note->id)->update([
                'creator_id' => $note->user_id,
                'creator_type' => \App\Models\User::class,
            ]);
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_notes', function (Blueprint $table) {
            // Remove the index
            $table->dropIndex(['creator_id', 'creator_type']);

            // Remove the polymorphic columns
            $table->dropColumn(['creator_id', 'creator_type']);
        });

        // Restore the user_id column
        Schema::table('project_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('project_id');
        });

        // Restore data from creator_id to user_id if possible
        $notes = \Illuminate\Support\Facades\DB::table('project_notes')->get();
        foreach ($notes as $note) {
            if ($note->creator_type === \App\Models\User::class) {
                \Illuminate\Support\Facades\DB::table('project_notes')
                    ->where('id', $note->id)
                    ->update(['user_id' => $note->creator_id]);
            }
        }

        // Note: Foreign key constraints are not restored here.
        // If they need to be restored, they should be added in a separate migration
        // or explicitly added here based on the original constraints.
    }
};
