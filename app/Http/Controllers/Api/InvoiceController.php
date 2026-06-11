<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesImageUploads;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceComment;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Services\MentionService;
use App\Services\XeroInvoiceService;
use App\Services\XeroAttachmentService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    use HandlesImageUploads, HasProjectPermissions;

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
            ->with(['client', 'invoiceItems.projectService', 'comments.user'])
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
        if (!$this->canEditInvoice($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to edit invoices.'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'total_amount' => 'nullable|numeric|min:0',
            'xero_branding_theme_id' => 'nullable|string',
            'xero_payment_service_ids' => 'nullable|array',
            'xero_payment_service_ids.*' => 'nullable|string|max:255|distinct',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480',
            'line_items' => 'nullable|array',
            'line_items.*.project_service_id' => 'required|exists:project_services,id',
            'line_items.*.milestone_key' => 'required|string|max:255',
            'line_items.*.label' => 'required|string|max:255',
            'line_items.*.description' => 'nullable|string',
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

        $currency = null;
        if ($lineItems->isNotEmpty()) {
            $serviceIds = collect($lineItems)->pluck('project_service_id')->unique();
            $services = ProjectService::whereIn('id', $serviceIds)->get();
            $currencies = $services->pluck('currency')->filter()->unique();

            if ($currencies->count() === 0 || $services->count() !== $currencies->count()) {
                throw ValidationException::withMessages([
                    'line_items' => 'One or more selected services do not have a currency defined. Cannot create invoice.',
                ]);
            }
            if ($currencies->count() > 1) {
                throw ValidationException::withMessages([
                    'line_items' => 'Selected services have different currencies. All line items in an invoice must share the same currency.',
                ]);
            }
            $currency = $currencies->first();
        } else {
            $service = ProjectService::where('project_id', $project->id)->whereNotNull('currency')->first();
            if (!$service) {
                throw ValidationException::withMessages([
                    'total_amount' => 'No currency defined in project services. Cannot create invoice.',
                ]);
            }
            $currency = $service->currency;
        }

        try {
            return DB::transaction(function () use ($project, $validated, $lineItems, $request, $currency) {
            $invoice = Invoice::create([
                'project_id' => $project->id,
                'client_id' => $validated['client_id'],
                'total_amount' => $lineItems->isNotEmpty()
                    ? 0
                    : (float) ($validated['total_amount'] ?? 0),
                'status' => 'pending_approval',
                'currency' => $currency,
                'xero_branding_theme_id' => $validated['xero_branding_theme_id'] ?? null,
                'xero_payment_service_ids' => collect($validated['xero_payment_service_ids'] ?? [])
                    ->filter(fn ($id) => filled($id))
                    ->values()
                    ->all(),
            ]);

            if ($request->hasFile('attachments')) {
                $paths = $this->uploadFilesToGcsWithThumbnails($request->file('attachments'), 'invoices', 'gcs');
                foreach ($paths as $uploadedFile) {
                    $invoice->files()->create($uploadedFile);
                }
            }

            if ($lineItems->isNotEmpty()) {
                $this->createInvoiceItems($invoice, $project, $lineItems->all());
                $invoice->total_amount = $invoice->invoiceItems()->sum(DB::raw('quantity * unit_price'));
                $invoice->save();
            }

            return response()->json($invoice->load(['client', 'invoiceItems.projectService', 'comments.user', 'files']), 201);
        });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'line_items' => 'One or more selected service milestones have already been invoiced. Please review existing invoices for this project before submitting.',
                ]);
            }

            throw $exception;
        }
    }

    public function approve(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can approve invoices.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        $validated = $request->validate([
            'review_comment' => 'nullable|string',
        ]);

        if ($invoice->status !== 'pending_approval') {
            return response()->json(['message' => 'Invoice is not in pending status.'], 400);
        }

        return DB::transaction(function () use ($invoice, $project, $user, $validated) {
            // 1. Push to Xero
            $xeroResult = $this->xeroInvoiceService->createSalesInvoice($invoice);
            $invoice->xero_invoice_id = $xeroResult['InvoiceID'];
            $invoice->invoice_number = $xeroResult['InvoiceNumber'];
            $invoice->status = 'authorised';
            $invoice->save();

            // 2. Push Attachments to Xero
            $this->xeroAttachmentService->uploadAttachments($invoice, $xeroResult['InvoiceID']);

            if (!empty($validated['review_comment'])) {
                try {
                    $this->xeroInvoiceService->addInvoiceNote($xeroResult['InvoiceID'], $validated['review_comment']);
                } catch (\Exception $e) {
                    // Log error but don't fail approval
                    Log::error('Failed to push review comment to Xero: ' . $e->getMessage());
                }
            }

            $xeroEmailSent = false;
            $xeroEmailError = null;
            try {
                $this->xeroInvoiceService->sendSalesInvoiceEmail($xeroResult['InvoiceID']);
                $xeroEmailSent = true;
            } catch (\Exception $e) {
                // Keep approval successful even if email dispatch fails.
                $xeroEmailError = $e->getMessage();
                Log::error('Failed to send invoice email via Xero', [
                    'invoice_id' => $invoice->id,
                    'xero_invoice_id' => $xeroResult['InvoiceID'],
                    'error' => $xeroEmailError,
                ]);
            }

            $this->recordReviewAction(
                $invoice,
                $project,
                $user,
                'approved',
                $validated['review_comment'] ?? null
            );

            $approvedInvoice = $invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user', 'files']);
            $payload = $approvedInvoice?->toArray() ?? [];
            $payload['xero_email_sent'] = $xeroEmailSent;
            if (!$xeroEmailSent && $xeroEmailError) {
                $payload['xero_email_error'] = $xeroEmailError;
            }

            return response()->json($payload);
        });
    }

    public function reject(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can reject invoices.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        $validated = $request->validate([
            'review_comment' => 'required|string',
        ]);

        if ($invoice->status !== 'pending_approval') {
            return response()->json(['message' => 'Invoice is not in pending status.'], 400);
        }

        return DB::transaction(function () use ($invoice, $project, $user, $validated) {
            $invoice->status = 'rejected';
            $invoice->save();

            $this->recordReviewAction($invoice, $project, $user, 'rejected', $validated['review_comment']);

            return response()->json($invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user']));
        });
    }

    public function comment(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can comment on invoices.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        $this->recordReviewAction($invoice, $project, $user, 'comment', $validated['comment']);

        if ($invoice->xero_invoice_id) {
            try {
                $this->xeroInvoiceService->addInvoiceNote($invoice->xero_invoice_id, $validated['comment']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to push comment to Xero: ' . $e->getMessage());
            }
        }

        return response()->json($invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user', 'files']));
    }

    public function show(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        if ($request->boolean('sync_xero') && $invoice->xero_invoice_id) {
            try {
                $invoice = $this->xeroInvoiceService->syncLocalInvoiceFromXero($invoice);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to sync invoice from Xero during show.', [
                    'invoice_id' => $invoice->id,
                    'xero_invoice_id' => $invoice->xero_invoice_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $invoice->load(['client', 'invoiceItems.projectService', 'comments.user', 'files']);
        $invoice->setAttribute('can_edit_invoice', $this->canEditInvoice($user, $project));
        $invoice->setAttribute('can_financially_edit_invoice', $this->canFinanciallyEditInvoice($invoice));

        return response()->json($invoice);
    }

    public function update(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if (!$this->canEditInvoice($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to edit invoices.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        if (! $this->canFinanciallyEditInvoice($invoice)) {
            return response()->json([
                'message' => 'This invoice can no longer be edited in CRM. Use Xero adjustment workflows instead.',
            ], 422);
        }

        $validated = $request->validate([
            'line_items' => 'required|array|min:1',
            'xero_payment_service_ids' => 'nullable|array',
            'xero_payment_service_ids.*' => 'nullable|string|max:255|distinct',
            'line_items.*.id' => 'nullable|integer|exists:invoice_items,id',
            'line_items.*.project_service_id' => 'required|integer|exists:project_services,id',
            'line_items.*.milestone_key' => 'required|string|max:255',
            'line_items.*.label' => 'required|string|max:255',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_type' => 'nullable|string|max:50',
            'line_items.*.description' => 'nullable|string|max:2000',
            'line_items.*.milestone_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        return DB::transaction(function () use ($invoice, $validated, $user, $project) {
            $currentItems = $invoice->invoiceItems()->get()->keyBy('id');
            $incomingItems = collect($validated['line_items']);

            $seenMilestones = [];
            foreach ($incomingItems as $lineItem) {
                $projectService = ProjectService::query()->whereKey((int) $lineItem['project_service_id'])->first();
                if (! $projectService || (int) $projectService->project_id !== (int) $project->id) {
                    return response()->json([
                        'message' => 'Each line item must belong to a service in the selected project.',
                    ], 422);
                }

                $milestoneKey = (string) $lineItem['milestone_key'];
                $compositeKey = $projectService->id.'|'.$milestoneKey;
                if (in_array($compositeKey, $seenMilestones, true)) {
                    return response()->json([
                        'message' => "Milestone {$milestoneKey} was added more than once for service {$projectService->id}.",
                    ], 422);
                }
                $seenMilestones[] = $compositeKey;

                $conflictingItem = InvoiceItem::query()
                    ->with('invoice')
                    ->where('project_service_id', $projectService->id)
                    ->where('milestone_key', $milestoneKey)
                    ->whereHas('invoice', function ($query) use ($invoice) {
                        $query->where('id', '!=', $invoice->id)
                            ->whereNotIn('status', ['rejected', 'voided']);
                    })
                    ->first();

                if ($conflictingItem) {
                    $existingInvoiceNumber = $conflictingItem->invoice?->invoice_number ?: ('ID '.$conflictingItem->invoice_id);
                    $existingInvoiceStatus = strtoupper((string) ($conflictingItem->invoice?->status ?? 'unknown'));

                    return response()->json([
                        'message' => "Milestone \"{$lineItem['label']}\" is already invoiced in invoice {$existingInvoiceNumber} ({$existingInvoiceStatus}).",
                    ], 422);
                }
            }

            $incomingExistingIds = $incomingItems
                ->pluck('id')
                ->filter(fn ($id) => ! is_null($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            // Remove lines omitted by the user in edit mode.
            $invoice->invoiceItems()
                ->whereNotIn('id', $incomingExistingIds->all())
                ->delete();

            foreach ($incomingItems as $lineItem) {
                $model = null;
                if (!empty($lineItem['id'])) {
                    $model = $currentItems->get((int) $lineItem['id']);
                    if (! $model) {
                        return response()->json([
                            'message' => 'One or more line items do not belong to this invoice.',
                        ], 422);
                    }
                }

                $payload = [
                    'project_service_id' => (int) $lineItem['project_service_id'],
                    'milestone_key' => (string) $lineItem['milestone_key'],
                    'label' => (string) $lineItem['label'],
                    'description' => $lineItem['description'] ?? null,
                    'quantity' => (float) $lineItem['quantity'],
                    'unit_price' => (float) $lineItem['unit_price'],
                    'tax_type' => $lineItem['tax_type'] ?? 'OUTPUT',
                ];

                if ($model) {
                    $model->fill($payload);
                    $model->save();
                } else {
                    $invoice->invoiceItems()->create($payload);
                }
            }

            $invoice->total_amount = (float) $invoice->invoiceItems()->sum(DB::raw('quantity * unit_price'));

            if (array_key_exists('xero_payment_service_ids', $validated)) {
                $invoice->xero_payment_service_ids = collect($validated['xero_payment_service_ids'] ?? [])
                    ->filter(fn ($id) => filled($id))
                    ->values()
                    ->all();
            }

            $invoice->save();

            $this->recordReviewAction(
                $invoice,
                $project,
                $user,
                'updated',
                'Invoice line items updated from CRM.'
            );

            $invoice->load(['client', 'invoiceItems.projectService', 'comments.user', 'files']);
            $invoice->setAttribute('can_edit_invoice', $this->canEditInvoice($user, $project));
            $invoice->setAttribute('can_financially_edit_invoice', $this->canFinanciallyEditInvoice($invoice));

            return response()->json($invoice);
        });
    }

    public function notes(Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        if (!$invoice->xero_invoice_id) {
            return response()->json(['notes' => []]);
        }

        try {
            $history = $this->xeroInvoiceService->getInvoiceHistory($invoice->xero_invoice_id);
            return response()->json(['notes' => $history]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch notes from Xero: ' . $e->getMessage()], 500);
        }
    }

    public function addNote(Request $request, Project $project, Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can add notes to invoices.'], 403);
        }

        $this->ensureProjectInvoice($project, $invoice);

        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        if (!$invoice->xero_invoice_id) {
            return response()->json(['message' => 'Invoice is not synced to Xero yet.'], 400);
        }

        try {
            $history = $this->xeroInvoiceService->addInvoiceNote($invoice->xero_invoice_id, $validated['note']);
            return response()->json($history);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to add note to Xero: ' . $e->getMessage()], 500);
        }
    }

    public function void(Invoice $invoice)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can void invoices.'], 403);
        }

        if ($invoice->status === 'voided') {
            return response()->json(['message' => 'Invoice is already voided.'], 400);
        }

        $invoice->status = 'voided';
        $invoice->save();

        return response()->json($invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user']));
    }

    public function all(Request $request)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('view_project_invoices')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Invoice::with(['project', 'client', 'invoiceItems.projectService', 'comments.user']);

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
            $serviceLabel = $projectService->crmService?->name ?: ('Service #'.$projectService->id);
            if (in_array($requestKey, $seenMilestones, true)) {
                throw ValidationException::withMessages([
                    'line_items' => "Milestone {$milestoneKey} was added more than once for {$serviceLabel}.",
                ]);
            }
            $seenMilestones[] = $requestKey;

            $existingInvoiceItem = InvoiceItem::query()
                ->with('invoice')
                ->where('project_service_id', $projectService->id)
                ->where('milestone_key', $milestoneKey)
                ->whereHas('invoice', function ($query) {
                    $query->whereNotIn('status', ['rejected', 'voided']);
                })
                ->first();

            if ($existingInvoiceItem) {
                $existingInvoiceNumber = $existingInvoiceItem->invoice?->invoice_number ?: ('ID '.$existingInvoiceItem->invoice_id);
                $existingInvoiceStatus = strtoupper((string) ($existingInvoiceItem->invoice?->status ?? 'unknown'));

                throw ValidationException::withMessages([
                    'line_items' => "Milestone \"{$lineItem['label']}\" for service \"{$serviceLabel}\" is already invoiced in invoice {$existingInvoiceNumber} ({$existingInvoiceStatus}).",
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
                'description' => $lineItem['description'] ?? null,
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

    protected function ensureProjectInvoice(Project $project, Invoice $invoice): void
    {
        if ((int) $invoice->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'invoice' => 'The selected invoice does not belong to the selected project.',
            ]);
        }
    }

    protected function canFinanciallyEditInvoice(Invoice $invoice): bool
    {
        return in_array($invoice->status, ['pending_approval', 'rejected', 'draft'], true);
    }

    protected function recordReviewAction(Invoice $invoice, Project $project, User $user, string $action, ?string $content): void
    {
        $invoiceComment = InvoiceComment::create([
            'invoice_id' => $invoice->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => $action,
            'content' => $content,
        ]);

        $mentionIds = [];
        if (filled($content)) {
            $mentionService = app(MentionService::class);
            $mentionIds = $mentionService->parseAndNotify($content, $invoiceComment);
        }

        if (empty($mentionIds)) {
            return;
        }

        $milestone = $project->supportMilestone();
        $taskType = TaskType::firstOrCreate(
            ['name' => 'Invoice Review'],
            ['created_by_user_id' => $user->id]
        );

        foreach (array_unique(array_map('intval', $mentionIds)) as $mentionedUserId) {
            if ($mentionedUserId <= 0) {
                continue;
            }

            Task::create([
                'name' => "Invoice #{$invoice->id} {$action}",
                'description' => trim((string) $content) !== ''
                    ? "Invoice #{$invoice->id} {$action} note:\n\n{$content}"
                    : "Invoice #{$invoice->id} was {$action}.",
                'assigned_to_user_id' => $mentionedUserId,
                'due_date' => now()->toDateString(),
                'status' => TaskStatus::ToDo,
                'task_type_id' => $taskType->id,
                'milestone_id' => $milestone->id,
                'creator_id' => $user->id,
                'creator_type' => User::class,
                'priority' => 'medium',
                'source' => 'invoice_review',
                'source_id' => $invoice->id,
            ]);
        }
    }
}
