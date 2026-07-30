<?php

    public function outstandingDocs(Request $request)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('view_project_transactions')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $bills = \App\Models\Bill::whereIn('status', [\App\Enums\BillStatus::Approved, \App\Enums\BillStatus::PartialPaid])
            ->with(['project:id,name', 'contractor:id,name'])
            ->get();
            
        $invoices = \App\Models\Invoice::whereIn('status', ['approved', 'sent', 'partial_paid'])
            ->with(['project:id,name', 'client:id,name'])
            ->get();

        return response()->json([
            'bills' => $bills,
            'invoices' => $invoices,
        ]);
    }
