<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmService;
use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
            'name' => 'sometimes|required|string|max:255|unique:crm_services,name,'.$crmService->id,
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

        $crmService->update([
            'name' => $validated['name'] ?? $crmService->name,
            'xero_item_code' => $validated['xero_item_code'] ?? null,
            'default_amount' => $validated['default_amount'] ?? $crmService->default_amount,
            'default_currency' => $validated['default_currency'] ?? $crmService->default_currency,
            'default_frequency' => $validated['default_frequency'] ?? $crmService->default_frequency,
            'default_payment_breakdown' => $validated['default_payment_breakdown'] ?? $crmService->default_payment_breakdown,
            'default_description' => $validated['default_description'] ?? $crmService->default_description,
            'default_xero_account_code' => $validated['default_xero_account_code'] ?? $crmService->default_xero_account_code,
        ]);

        return response()->json($crmService);
    }

    public function merge(Request $request, CrmService $crmService): JsonResponse
    {
        $validated = $request->validate([
            'target_crm_service_id' => 'required|exists:crm_services,id',
        ]);

        $targetServiceId = (int) $validated['target_crm_service_id'];
        if ($targetServiceId === (int) $crmService->id) {
            return response()->json([
                'message' => 'A CRM service cannot be merged into itself.',
            ], 422);
        }

        $targetService = CrmService::query()->findOrFail($targetServiceId);
        $movedCount = 0;

        DB::transaction(function () use ($crmService, $targetService, &$movedCount) {
            $movedCount = ProjectService::query()
                ->where('crm_service_id', $crmService->id)
                ->update(['crm_service_id' => $targetService->id]);

            if (! $targetService->xero_item_code && $crmService->xero_item_code) {
                $targetService->update(['xero_item_code' => $crmService->xero_item_code]);
            }

            $crmService->delete();
        });

        return response()->json([
            'message' => 'CRM service merged successfully.',
            'moved_project_services_count' => $movedCount,
            'target_service_id' => $targetService->id,
            'target_service_name' => $targetService->name,
        ]);
    }
}
