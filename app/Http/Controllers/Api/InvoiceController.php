<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\XeroInvoiceService;
use App\Services\XeroAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use HasProjectPermissions;

    public function __construct(
        private readonly XeroInvoiceService $xeroInvoiceService,
        private readonly XeroAttachmentService $xeroAttachmentService
    ) {}

    public function index(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $invoices = Invoice::where('project_id', $project->id)
            ->with(['client'])
            ->latest()
            ->get();

        return response()->json($invoices);
    }

    public function store(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'total_amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = Invoice::create([
            'project_id' => $project->id,
            'client_id' => $validated['client_id'],
            'total_amount' => $validated['total_amount'],
            'status' => 'pending_approval',
        ]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json($invoice, 201);
    }

    public function approve(Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can approve invoices.'], 403);
        }

        if ($invoice->status !== 'pending_approval') {
            return response()->json(['message' => 'Invoice is not in pending status.'], 400);
        }

        return DB::transaction(function () use ($invoice) {
            // 1. Push to Xero
            $xeroInvoiceId = $this->xeroInvoiceService->createSalesInvoice($invoice);
            $invoice->xero_invoice_id = $xeroInvoiceId;
            $invoice->status = 'authorised';
            $invoice->save();

            // 2. Push Attachments to Xero
            $this->xeroAttachmentService->uploadAttachments($invoice, $xeroInvoiceId);

            return response()->json($invoice->fresh(['client']));
        });
    }

    public function all(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Invoice::with(['project', 'client']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }
}
