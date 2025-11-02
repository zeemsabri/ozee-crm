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
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'project_type')) {
                $table->string('project_type')->nullable()->after('description');
            }
            if (! Schema::hasColumn('projects', 'departments')) {
                $table->json('departments')->nullable()->after('project_type');
            }
            if (! Schema::hasColumn('projects', 'source')) {
                $table->string('source')->nullable()->after('departments');
            }
            if (! Schema::hasColumn('projects', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('projects', 'contract_details')) {
                $table->longText('contract_details')->nullable()->after('total_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'project_type',
                'departments',
                'source',
                'total_amount',
                'contract_details',
            ]);
        });
    }
};
