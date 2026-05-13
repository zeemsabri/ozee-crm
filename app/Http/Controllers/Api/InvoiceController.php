<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectService;
use App\Services\XeroInvoiceService;
use App\Services\XeroAttachmentService;
use Illuminate\Validation\ValidationException;
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
            ->with(['client', 'invoiceItems.projectService'])
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
            'total_amount' => 'nullable|numeric|min:0',
            'line_items' => 'nullable|array',
            'line_items.*.project_service_id' => 'required|exists:project_services,id',
            'line_items.*.milestone_key' => 'required|string|max:255',
            'line_items.*.label' => 'required|string|max:255',
            'line_items.*.quantity' => 'nullable|numeric|min:0.01',
            'line_items.*.unit_price' => 'nullable|numeric|min:0',
            'line_items.*.milestone_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_type' => 'nullable|string|max:50',
        ]);

        $lineItems = collect($validated['line_items'] ?? []);

        if ($lineItems->isEmpty() && ! array_key_exists('total_amount', $validated)) {
            throw ValidationException::withMessages([
                'line_items' => 'Add at least one service milestone or provide a total amount.',
            ]);
        }

        return DB::transaction(function () use ($project, $validated, $lineItems, $request) {
            $invoice = Invoice::create([
                'project_id' => $project->id,
                'client_id' => $validated['client_id'],
                'total_amount' => $lineItems->isNotEmpty()
                    ? 0
                    : (float) ($validated['total_amount'] ?? 0),
                'status' => 'pending_approval',
            ]);

            if ($lineItems->isNotEmpty()) {
                $this->createInvoiceItems($invoice, $project, $lineItems->all());
                $invoice->total_amount = $invoice->invoiceItems()->sum(DB::raw('quantity * unit_price'));
                $invoice->save();
            }

            if ($request->header('X-Inertia')) {
                return back();
            }

            return response()->json($invoice->load(['client', 'invoiceItems.projectService']), 201);
        });
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

            return response()->json($invoice->fresh(['client', 'invoiceItems.projectService']));
        });
    }

    public function all(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Invoice::with(['project', 'client', 'invoiceItems.projectService']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    protected function createInvoiceItems(Invoice $invoice, Project $project, array $lineItems): void
    {
        $seenMilestones = [];
        $normalizedItems = collect($lineItems)->map(function (array $lineItem) use ($project, &$seenMilestones) {
            $projectService = ProjectService::query()->whereKey($lineItem['project_service_id'])->firstOrFail();

            if ((int) $projectService->project_id !== (int) $project->id) {
                throw ValidationException::withMessages([
                    'line_items' => 'Each invoice line item must belong to the selected project.',
                ]);
            }

            $milestoneKey = (string) $lineItem['milestone_key'];
            $requestKey = $projectService->id.'|'.$milestoneKey;
            if (in_array($requestKey, $seenMilestones, true)) {
                throw ValidationException::withMessages([
                    'line_items' => "Milestone {$milestoneKey} was added more than once for {$projectService->service_id}.",
                ]);
            }
            $seenMilestones[] = $requestKey;

            $duplicateExists = InvoiceItem::query()
                ->where('project_service_id', $projectService->id)
                ->where('milestone_key', $milestoneKey)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'line_items' => "Milestone {$milestoneKey} has already been invoiced for {$projectService->service_id}.",
                ]);
            }

            $quantity = (float) ($lineItem['quantity'] ?? 1);
            $unitPrice = array_key_exists('unit_price', $lineItem) && is_numeric($lineItem['unit_price'])
                ? (float) $lineItem['unit_price']
                : $this->calculateMilestoneAmount($projectService, $lineItem);

            return [
                'project_service_id' => $projectService->id,
                'milestone_key' => $milestoneKey,
                'label' => (string) $lineItem['label'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_type' => $lineItem['tax_type'] ?? 'OUTPUT',
            ];
        });

        $invoice->total_amount = $normalizedItems->sum(fn (array $item) => (float) $item['quantity'] * (float) $item['unit_price']);
        $invoice->save();

        foreach ($normalizedItems as $item) {
            $invoice->invoiceItems()->create($item);
        }
    }

    protected function calculateMilestoneAmount(ProjectService $projectService, array $lineItem): float
    {
        $percentage = (float) ($lineItem['milestone_percentage'] ?? 0);

        return round(((float) $projectService->amount * $percentage) / 100, 2);
    }
}
