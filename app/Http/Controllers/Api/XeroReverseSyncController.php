<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrmService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectService;
use App\Services\ExistingClientEnquiryService;
use App\Services\XeroInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XeroReverseSyncController extends Controller
{
    public function __construct(
        private readonly XeroInvoiceService $xeroInvoiceService,
        private readonly ExistingClientEnquiryService $enquiryService
    ) {
    }

    public function showXeroInvoice(string $xeroInvoiceId): JsonResponse
    {
        try {
            $invoice = $this->xeroInvoiceService->getSalesInvoiceFromXero($xeroInvoiceId);
            
            $lineItems = collect(data_get($invoice, 'LineItems', []))->map(function ($li, $index) {
                return [
                    'index' => $index,
                    'description' => data_get($li, 'Description'),
                    'quantity' => (float) data_get($li, 'Quantity', 1),
                    'unit_amount' => (float) data_get($li, 'UnitAmount', 0),
                    'tax_type' => data_get($li, 'TaxType'),
                ];
            })->all();

            return response()->json([
                'xero_invoice_id' => data_get($invoice, 'InvoiceID'),
                'invoice_number' => data_get($invoice, 'InvoiceNumber'),
                'contact_name' => data_get($invoice, 'Contact.Name'),
                'contact_id' => data_get($invoice, 'Contact.ContactID'),
                'contact_email' => data_get($invoice, 'Contact.EmailAddress'),
                'date' => data_get($invoice, 'DateString') ?? data_get($invoice, 'Date'),
                'due_date' => data_get($invoice, 'DueDateString') ?? data_get($invoice, 'DueDate'),
                'total_amount' => (float) data_get($invoice, 'Total'),
                'status' => data_get($invoice, 'Status'),
                'currency' => data_get($invoice, 'CurrencyCode', 'USD'),
                'line_items' => $lineItems,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch full Xero invoice details: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch invoice details: ' . $e->getMessage()], 500);
        }
    }

    public function listXeroInvoices(Request $request): JsonResponse
    {
        try {
            $xeroInvoices = $this->xeroInvoiceService->getRecentInvoicesFromXero();
            
            // Get all existing local invoice xero_invoice_ids
            $syncedXeroIds = Invoice::whereNotNull('xero_invoice_id')
                ->pluck('xero_invoice_id')
                ->all();

            $formattedInvoices = collect($xeroInvoices)->map(function ($item) use ($syncedXeroIds) {
                $xeroInvoiceId = data_get($item, 'InvoiceID');
                $isSynced = in_array($xeroInvoiceId, $syncedXeroIds, true);

                $xeroContactId = data_get($item, 'Contact.ContactID');
                $xeroContactName = data_get($item, 'Contact.Name');
                $xeroContactEmail = data_get($item, 'Contact.EmailAddress');

                // Try to resolve client
                $matchedClient = null;
                if ($xeroContactId) {
                    $matchedClient = Client::where('xero_contact_id', $xeroContactId)->first();
                }

                // If not matched, try matching by name
                if (!$matchedClient && $xeroContactName) {
                    $matchedClient = Client::where('name', 'like', '%' . $xeroContactName . '%')->first();
                }

                $matchedProjectId = null;
                $candidateProjects = [];

                if ($matchedClient) {
                    $candidateProjects = Project::whereHas('clients', function ($query) use ($matchedClient) {
                        $query->where('clients.id', $matchedClient->id);
                    })->select(['id', 'name'])->get()->toArray();

                    if (count($candidateProjects) === 1) {
                        $matchedProjectId = $candidateProjects[0]['id'];
                    }
                }

                // Get line items list
                $lineItems = collect(data_get($item, 'LineItems', []))->map(function ($li, $index) {
                    return [
                        'index' => $index,
                        'description' => data_get($li, 'Description'),
                        'quantity' => (float) data_get($li, 'Quantity', 1),
                        'unit_amount' => (float) data_get($li, 'UnitAmount', 0),
                        'tax_type' => data_get($li, 'TaxType'),
                    ];
                })->all();

                return [
                    'xero_invoice_id' => $xeroInvoiceId,
                    'invoice_number' => data_get($item, 'InvoiceNumber'),
                    'contact_name' => $xeroContactName,
                    'contact_id' => $xeroContactId,
                    'contact_email' => $xeroContactEmail,
                    'date' => data_get($item, 'DateString') ?? data_get($item, 'Date'),
                    'due_date' => data_get($item, 'DueDateString') ?? data_get($item, 'DueDate'),
                    'total_amount' => (float) data_get($item, 'Total'),
                    'status' => data_get($item, 'Status'),
                    'is_already_synced' => $isSynced,
                    'suggested_client' => $matchedClient ? [
                        'id' => $matchedClient->id,
                        'name' => $matchedClient->name,
                    ] : null,
                    'suggested_project_id' => $matchedProjectId,
                    'candidate_projects' => $candidateProjects,
                    'line_items' => $lineItems,
                ];
            });

            $projects = Project::orderBy('name')->select(['id', 'name'])->get();
            $crmServices = CrmService::orderBy('name')->get();
            $clients = Client::orderBy('name')->select(['id', 'name'])->get();

            return response()->json([
                'invoices' => $formattedInvoices,
                'projects' => $projects,
                'crm_services' => $crmServices,
                'clients' => $clients,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to list Xero invoices: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to retrieve invoices from Xero. Please ensure Xero is connected.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createClientFromXeroContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'xero_contact_id' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|max:255',
        ]);

        try {
            $client = Client::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'xero_contact_id' => $validated['xero_contact_id'],
                'xero_contact_name' => $validated['name'],
                'xero_contact_email' => $validated['email'],
                'xero_synced_at' => now(),
            ]);

            return response()->json([
                'message' => 'Client created and linked successfully.',
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                ]
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create client from Xero contact: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create client: ' . $e->getMessage()], 500);
        }
    }

    public function getProjectServices(Project $project): JsonResponse
    {
        $services = ProjectService::where('project_id', $project->id)
            ->where('status', 'active')
            ->with('crmService')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->crmService ? $service->crmService->name : $service->description,
                ];
            });

        return response()->json($services);
    }

    public function quickAddProjectService(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'crm_service_id' => 'required|exists:crm_services,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'frequency' => 'nullable|string|in:monthly,one_off',
            'description' => 'nullable|string|max:5000',
            'xero_account_code' => 'nullable|string|max:50',
        ]);

        try {
            $crmService = CrmService::findOrFail($validated['crm_service_id']);

            // Get existing service details for project
            $currentDetails = $this->enquiryService->projectServiceDetails($project);

            $newDetail = [
                'enquiry_id' => (string) Str::uuid(),
                'service_id' => $crmService->name,
                'amount' => (float) $validated['amount'],
                'currency' => $validated['currency'] ?? $project->currency ?? 'USD',
                'frequency' => $validated['frequency'] ?? 'one_off',
                'description' => $validated['description'] ?? '',
                'xero_account_code' => $validated['xero_account_code'] ?? $crmService->default_xero_account_code,
                'status' => 'active',
                'service_tracking_type' => 'operational_service',
            ];

            $currentDetails[] = $newDetail;

            $this->enquiryService->saveProjectServiceDetails($project, $currentDetails);

            $newService = ProjectService::where('project_id', $project->id)
                ->where('crm_service_id', $crmService->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            return response()->json([
                'message' => 'Project service added successfully.',
                'service' => [
                    'id' => $newService->id,
                    'name' => $crmService->name,
                ]
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Quick-add project service failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to add project service: ' . $e->getMessage()], 500);
        }
    }

    public function syncInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'xero_invoice_id' => 'required|string',
            'project_id' => 'required|exists:projects,id',
            'line_items' => 'required|array',
            'line_items.*.index' => 'required|integer',
            'line_items.*.project_service_id' => 'nullable|exists:project_services,id',
            'line_items.*.new_service' => 'nullable|array',
            'line_items.*.new_service.crm_service_id' => 'required_without:line_items.*.project_service_id|exists:crm_services,id',
            'line_items.*.new_service.amount' => 'required_without:line_items.*.project_service_id|numeric|min:0',
            'line_items.*.new_service.currency' => 'nullable|string|max:10',
            'line_items.*.new_service.frequency' => 'nullable|string|in:monthly,one_off',
            'line_items.*.new_service.description' => 'nullable|string|max:5000',
            'line_items.*.new_service.xero_account_code' => 'nullable|string|max:50',
        ]);

        $xeroInvoiceId = $validated['xero_invoice_id'];
        $projectId = $validated['project_id'];

        // Check if invoice already exists
        if (Invoice::where('xero_invoice_id', $xeroInvoiceId)->exists()) {
            return response()->json(['message' => 'Invoice has already been synced.'], 422);
        }

        try {
            // Fetch full Xero invoice to get exact line items
            $xeroInvoice = $this->xeroInvoiceService->getSalesInvoiceFromXero($xeroInvoiceId);
            $project = Project::findOrFail($projectId);

            // Resolve client
            $project->loadMissing('clients');
            $client = $project->clients->first();
            if (!$client && $project->client_id) {
                $client = Client::find($project->client_id);
            }

            if (!$client) {
                return response()->json(['message' => 'Selected project does not have an associated client.'], 422);
            }

            $localInvoice = DB::transaction(function () use ($xeroInvoice, $project, $client, $validated, $xeroInvoiceId) {
                $mappedItems = collect($validated['line_items'])->keyBy('index');
                $xeroLineItems = data_get($xeroInvoice, 'LineItems', []);

                $localInvoice = new Invoice([
                    'project_id' => $project->id,
                    'client_id' => $client->id,
                    'total_amount' => (float) data_get($xeroInvoice, 'Total', 0),
                    'status' => $this->xeroInvoiceService->mapSalesInvoiceStatus($xeroInvoice),
                    'xero_invoice_id' => $xeroInvoiceId,
                    'invoice_number' => data_get($xeroInvoice, 'InvoiceNumber'),
                    'currency' => data_get($xeroInvoice, 'CurrencyCode', 'USD'),
                    'line_amount_type' => $this->xeroInvoiceService->normalizeLineAmountType(data_get($xeroInvoice, 'LineAmountTypes')),
                ]);

                $xeroDate = data_get($xeroInvoice, 'DateString') ?? data_get($xeroInvoice, 'Date');
                if ($xeroDate) {
                    $localInvoice->created_at = \Illuminate\Support\Carbon::parse($xeroDate);
                }

                $localInvoice->save();

                foreach ($xeroLineItems as $index => $item) {
                    if (!$mappedItems->has($index)) {
                        continue;
                    }

                    $mapping = $mappedItems->get($index);
                    $projectServiceId = $mapping['project_service_id'] ?? null;

                    // If user chose to create a new service inline
                    if (!$projectServiceId && !empty($mapping['new_service'])) {
                        $newServiceData = $mapping['new_service'];
                        $crmService = CrmService::findOrFail($newServiceData['crm_service_id']);

                        $currentDetails = $this->enquiryService->projectServiceDetails($project);
                        $newDetail = [
                            'enquiry_id' => (string) Str::uuid(),
                            'service_id' => $crmService->name,
                            'amount' => (float) $newServiceData['amount'],
                            'currency' => $newServiceData['currency'] ?? $project->currency ?? 'USD',
                            'frequency' => $newServiceData['frequency'] ?? 'one_off',
                            'description' => $newServiceData['description'] ?? '',
                            'xero_account_code' => $newServiceData['xero_account_code'] ?? $crmService->default_xero_account_code,
                            'status' => 'active',
                            'service_tracking_type' => 'operational_service',
                        ];
                        $currentDetails[] = $newDetail;

                        $this->enquiryService->saveProjectServiceDetails($project, $currentDetails);

                        $newService = ProjectService::where('project_id', $project->id)
                            ->where('crm_service_id', $crmService->id)
                            ->where('status', 'active')
                            ->latest()
                            ->first();

                        $projectServiceId = $newService->id;
                    }

                    if ($projectServiceId) {
                        InvoiceItem::create([
                            'invoice_id' => $localInvoice->id,
                            'project_service_id' => $projectServiceId,
                            'milestone_key' => 'xero_sync_' . Str::random(8),
                            'label' => data_get($item, 'Description') ?: 'Sync line item',
                            'description' => data_get($item, 'Description'),
                            'quantity' => (float) data_get($item, 'Quantity', 1),
                            'unit_price' => (float) data_get($item, 'UnitAmount', 0),
                            'tax_type' => $this->xeroInvoiceService->normalizeInvoiceTaxType(data_get($item, 'TaxType')),
                        ]);
                    }
                }

                return $localInvoice;
            });

            return response()->json([
                'message' => 'Invoice synced successfully.',
                'invoice' => $localInvoice->load('invoiceItems')
            ]);
        } catch (\Throwable $e) {
            Log::error('Reverse sync of Xero invoice failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }
}
