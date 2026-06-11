<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\XeroConnection;
use App\Services\XeroAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class XeroConnectionController extends Controller
{
    public function __construct(private readonly XeroAuthService $xeroAuthService) {}

    public function index(Request $request): Response
    {
        $this->ensureUserHasAccess($request);

        $connection = XeroConnection::query()
            ->with(['connectedBy:id,name,email', 'tenants'])
            ->where('provider', XeroConnection::PROVIDER)
            ->first();

        $brandingThemes = [];
        if ($connection?->status === 'connected') {
            try {
                $xeroInvoiceService = app(\App\Services\XeroInvoiceService::class);
                $brandingThemes = $xeroInvoiceService->getBrandingThemes();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to fetch Xero branding themes: ' . $e->getMessage());
            }
        }

        $transactionTypes = \App\Models\TransactionType::query()
            ->withCount(['transactions', 'bills'])
            ->with([
                'transactions' => fn ($query) => $query
                    ->select(['id', 'transaction_type_id', 'project_id'])
                    ->with(['project:id,name'])
                    ->latest('id'),
                'bills' => fn ($query) => $query
                    ->select(['id', 'transaction_type_id', 'project_id'])
                    ->with(['project:id,name'])
                    ->latest('id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                $projectNames = $type->transactions
                    ->pluck('project.name')
                    ->merge($type->bills->pluck('project.name'))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'xero_account_code' => $type->xero_account_code,
                    'transactions_count' => $type->transactions_count,
                    'bills_count' => $type->bills_count,
                    'usage_projects' => $projectNames,
                    'transaction_usages' => $type->transactions
                        ->take(5)
                        ->map(fn ($transaction) => [
                            'id' => $transaction->id,
                            'project_name' => $transaction->project?->name,
                        ])
                        ->values(),
                    'bill_usages' => $type->bills
                        ->take(5)
                        ->map(fn ($bill) => [
                            'id' => $bill->id,
                            'project_name' => $bill->project?->name,
                        ])
                        ->values(),
                ];
            })
            ->values();

        $crmServices = \App\Models\CrmService::query()
            ->withCount('projectServices')
            ->with([
                'projectServices' => fn ($query) => $query
                    ->select(['id', 'crm_service_id', 'project_id'])
                    ->with(['project:id,name'])
                    ->latest('id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($service) {
                $projectNames = $service->projectServices
                    ->pluck('project.name')
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'xero_item_code' => $service->xero_item_code,
                    'project_services_count' => $service->project_services_count,
                    'usage_projects' => $projectNames,
                    'project_service_usages' => $service->projectServices
                        ->take(5)
                        ->map(fn ($projectService) => [
                            'id' => $projectService->id,
                            'project_name' => $projectService->project?->name,
                        ])
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Xero/Index', [
            'connection' => $connection,
            'transaction_types' => $transactionTypes,
            'crm_services' => $crmServices,
            'branding_themes' => $brandingThemes,
        ]);
    }

    public function brandingThemes(Request $request): JsonResponse
    {

        $connection = XeroConnection::query()
            ->where('provider', XeroConnection::PROVIDER)
            ->first();

        if ($connection?->status !== 'connected') {
            return response()->json(['branding_themes' => [], 'default_branding_theme_id' => null]);
        }

        try {
            $xeroInvoiceService = app(\App\Services\XeroInvoiceService::class);
            $brandingThemes = $xeroInvoiceService->getBrandingThemes();
            return response()->json([
                'branding_themes' => $brandingThemes,
                'default_branding_theme_id' => $connection->default_branding_theme_id,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch Xero branding themes API: ' . $e->getMessage());
            return response()->json(['branding_themes' => [], 'default_branding_theme_id' => null], 500);
        }
    }

    public function saveDefaultBrandingTheme(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'default_branding_theme_id' => 'nullable|string',
        ]);

        $connection = XeroConnection::query()
            ->where('provider', XeroConnection::PROVIDER)
            ->firstOrFail();

        $connection->default_branding_theme_id = $validated['default_branding_theme_id'];
        $connection->save();

        return back()->with('success', 'Default branding theme saved successfully.');
    }

    public function connect(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        return redirect()->away($this->xeroAuthService->beginAuthorization($request->user()));
    }

    public function status(Request $request): JsonResponse
    {

        $connection = XeroConnection::query()
            ->where('provider', XeroConnection::PROVIDER)
            ->first();

        $status = $connection?->status ?? 'disconnected';

        return response()->json([
            'status' => $status,
            'connected' => $status === 'connected',
            'selected_tenant_name' => $connection?->selected_tenant_name,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required_without:error|string',
            'state' => 'required|string',
            'error' => 'nullable|string',
        ]);

        if ($request->filled('error')) {
            return redirect()->route('admin.xero.index')
                ->with('error', 'Xero authorization was declined or failed: '.$request->string('error'));
        }

        $connection = $this->xeroAuthService->handleCallback(
            $request->string('code')->toString(),
            $request->string('state')->toString()
        );

        $message = $connection->selected_tenant_id
            ? 'Xero connected successfully.'
            : 'Xero connected. Select a Xero organization to finish setup.';

        return redirect()->route('admin.xero.index')->with('success', $message);
    }

    public function selectTenant(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'tenant_id' => 'required|string',
        ]);

        try {
            $this->xeroAuthService->selectTenant($validated['tenant_id']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Xero organization selected successfully.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $this->xeroAuthService->disconnect();

        return back()->with('success', 'Xero connection disconnected successfully.');
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function ensureUserHasAccess(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('configure_xero_settings'), 403);
    }
}
