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

    public function update(Request $request, CrmService $crmService): JsonResponse
    {
        $validated = $request->validate([
            'xero_item_code' => 'nullable|string|max:50',
        ]);

        $crmService->update($validated);

        return response()->json($crmService);
    }
}
