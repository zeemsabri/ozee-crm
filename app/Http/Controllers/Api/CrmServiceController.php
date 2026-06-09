<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CrmServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CrmService::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_services,name',
            'xero_item_code' => 'nullable|string|max:50',
            'default_amount' => 'nullable|numeric|min:0',
            'default_currency' => 'nullable|string|max:10',
            'default_frequency' => 'nullable|string|in:monthly,one_off',
            'default_payment_breakdown' => 'nullable|array',
            'default_payment_breakdown.*.label' => 'nullable|string|max:255',
            'default_payment_breakdown.*.percentage' => 'nullable|integer|min:0|max:100',
            'default_payment_breakdown.*.due_date' => 'nullable|date',
            'default_description' => 'nullable|string|max:5000',
            'default_xero_account_code' => 'nullable|string|max:50',
        ]);

        $service = CrmService::create($validated);

        return response()->json($service, 201);
    }

    public function update(Request $request, CrmService $crmService): JsonResponse
    {
        $validated = $request->validate([
            'xero_item_code' => 'nullable|string|max:50',
            'default_amount' => 'nullable|numeric|min:0',
            'default_currency' => 'nullable|string|max:10',
            'default_frequency' => 'nullable|string|in:monthly,one_off',
            'default_payment_breakdown' => 'nullable|array',
            'default_payment_breakdown.*.label' => 'nullable|string|max:255',
            'default_payment_breakdown.*.percentage' => 'nullable|integer|min:0|max:100',
            'default_payment_breakdown.*.due_date' => 'nullable|date',
            'default_description' => 'nullable|string|max:5000',
            'default_xero_account_code' => 'nullable|string|max:50',
        ]);

        $crmService->update($validated);

        return response()->json($crmService);
    }
}
