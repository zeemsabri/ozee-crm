<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyBudget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MonthlyBudgetController extends Controller
{
    /**
     * Display a listing of the monthly budgets.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $this->authorize('viewAny', MonthlyBudget::class);

        return Inertia::render('Admin/MonthlyBudgets/Index');
    }

    /**
     * Store a newly created monthly budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', MonthlyBudget::class);

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'total_budget_pkr' => 'required|numeric|min:0',
            'consistent_contributor_pool_pkr' => 'required|numeric|min:0',
            'high_achiever_pool_pkr' => 'required|numeric|min:0',
            'team_total_points' => 'required|numeric|min:0',
            'points_value_pkr' => 'required|numeric|min:0',
            'most_improved_award_pkr' => 'required|numeric|min:0',
            'first_place_award_pkr' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if a budget already exists for this year/month
        $existingBudget = MonthlyBudget::where('year', $request->year)
            ->where('month', $request->month)
            ->first();

        if ($existingBudget) {
            return response()->json([
                'message' => 'A budget already exists for this month and year.',
                'errors' => ['year' => ['A budget already exists for this month and year.']]
            ], 422);
        }

        $monthlyBudget = MonthlyBudget::create($request->all());

        return response()->json([
            'message' => 'Monthly budget created successfully!',
            'monthlyBudget' => $monthlyBudget
        ], 201);
    }

    /**
     * Display the specified monthly budget.
     *
     * @param  \App\Models\MonthlyBudget  $monthlyBudget
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(MonthlyBudget $monthlyBudget)
    {
        $this->authorize('view', $monthlyBudget);

        return response()->json($monthlyBudget);
    }

    /**
     * Update the specified monthly budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MonthlyBudget  $monthlyBudget
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, MonthlyBudget $monthlyBudget)
    {
        $this->authorize('update', $monthlyBudget);

        $validator = Validator::make($request->all(), [
            'total_budget_pkr' => 'required|numeric|min:0',
            'consistent_contributor_pool_pkr' => 'required|numeric|min:0',
            'high_achiever_pool_pkr' => 'required|numeric|min:0',
            'team_total_points' => 'required|numeric|min:0',
            'points_value_pkr' => 'required|numeric|min:0',
            'most_improved_award_pkr' => 'required|numeric|min:0',
            'first_place_award_pkr' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $monthlyBudget->update($request->all());

        return response()->json([
            'message' => 'Monthly budget updated successfully!',
            'monthlyBudget' => $monthlyBudget
        ]);
    }

    /**
     * Remove the specified monthly budget from storage.
     *
     * @param  \App\Models\MonthlyBudget  $monthlyBudget
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(MonthlyBudget $monthlyBudget)
    {
        $this->authorize('delete', $monthlyBudget);

        $monthlyBudget->delete();

        return response()->json([
            'message' => 'Monthly budget deleted successfully!'
        ]);
    }

    /**
     * Get all monthly budgets.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllBudgets()
    {
        $this->authorize('viewAny', MonthlyBudget::class);

        $budgets = MonthlyBudget::orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json($budgets);
    }

    /**
     * Get the current month's budget.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentBudget()
    {
        $this->authorize('viewAny', MonthlyBudget::class);

        $currentYear = date('Y');
        $currentMonth = date('n');

        $budget = MonthlyBudget::where('year', $currentYear)
            ->where('month', $currentMonth)
            ->first();

        if (!$budget) {
            return response()->json([
                'message' => 'No budget found for the current month.'
            ], 404);
        }

        return response()->json($budget);
    }
}
