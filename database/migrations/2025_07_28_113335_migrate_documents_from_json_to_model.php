<?php

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates document data from JSON field in Project model to Document model.
     */
    public function up(): void
    {

    }

    /**
     * Reverse the migrations.
     * This is a one-way migration, so down() doesn't restore the JSON data.
     * The documents table would need to be manually emptied if needed.
     */
    public function down(): void
    {

    }
};
