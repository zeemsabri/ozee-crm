<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyBudget;
use App\Services\BonusCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BonusCalculatorController extends Controller
{
    /**
     * Show the bonus calculation page.
     */
    public function index()
    {
        // Reuse the same permission gate as Monthly Budgets viewing
        $this->authorize('viewAny', MonthlyBudget::class);

        return Inertia::render('Admin/BonusCalculator/Index');
    }

    /**
     * Calculate bonuses for a given year and month and return JSON stats.
     */
    public function calculate(Request $request, BonusCalculationService $service)
    {
        $this->authorize('viewAny', MonthlyBudget::class);

        $year = (int) ($request->query('year') ?? date('Y'));
        $month = (int) ($request->query('month') ?? date('n'));

        $result = $service->calculateMonthlyBonuses($year, $month);

        return response()->json($result);
    }
}
