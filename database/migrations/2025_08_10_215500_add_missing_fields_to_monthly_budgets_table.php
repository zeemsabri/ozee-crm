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
        Schema::table('monthly_budgets', function (Blueprint $table) {
            $table->integer('number_of_employees')->default(0)->after('total_budget_pkr');
            $table->integer('number_of_contractors')->default(0)->after('number_of_employees');
            $table->string('employee_pool_input')->nullable()->after('number_of_contractors');

            $table->decimal('employee_bonus_pool_pkr', 10, 2)->default(0)->after('employee_pool_input');
            $table->decimal('contractor_bonus_pool_pkr', 10, 2)->default(0)->after('employee_bonus_pool_pkr');

            $table->decimal('second_place_award_pkr', 10, 2)->default(0)->after('first_place_award_pkr');
            $table->decimal('third_place_award_pkr', 10, 2)->default(0)->after('second_place_award_pkr');
            $table->decimal('contractor_of_the_month_award_pkr', 10, 2)->default(0)->after('third_place_award_pkr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_budgets', function (Blueprint $table) {
            $table->dropColumn([
                'number_of_employees',
                'number_of_contractors',
                'employee_pool_input',
                'employee_bonus_pool_pkr',
                'contractor_bonus_pool_pkr',
                'second_place_award_pkr',
                'third_place_award_pkr',
                'contractor_of_the_month_award_pkr',
            ]);
        });
    }
};
