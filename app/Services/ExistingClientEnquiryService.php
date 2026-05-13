<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExistingClientEnquiryService
{
    public const TRACKING_OPERATIONAL = 'operational_service';

    public const TRACKING_ENQUIRY = 'client_enquiry';

    public const STATUS_PENDING_QUOTE = 'pending_quote';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONVERTED = 'converted_to_service';

    public const CONVERSION_TASK = 'task';

    public const CONVERSION_MILESTONE = 'milestone';

    public const CONVERSION_PROJECT = 'project';

    public function normalizeServiceDetails(?array $serviceDetails, ?string $defaultCurrency = null): array
    {
        return collect($serviceDetails ?? [])
            ->filter(fn ($detail) => is_array($detail) && filled($detail['service_id'] ?? null))
            ->map(fn (array $detail) => $this->normalizeServiceDetail($detail, $defaultCurrency))
            ->values()
            ->all();
    }

    public function normalizeServiceDetail(array $detail, ?string $defaultCurrency = null): array
    {
        $trackingType = in_array($detail['service_tracking_type'] ?? null, [self::TRACKING_OPERATIONAL, self::TRACKING_ENQUIRY], true)
            ? $detail['service_tracking_type']
            : self::TRACKING_OPERATIONAL;

        $showOnLeadsBoard = $trackingType === self::TRACKING_ENQUIRY
            ? (bool) ($detail['show_on_leads_board'] ?? true)
            : false;

        $status = $trackingType === self::TRACKING_ENQUIRY
            ? $this->normalizeEnquiryStatus($detail['enquiry_status'] ?? self::STATUS_PENDING_QUOTE)
            : null;

        $paymentBreakdown = $this->normalizePaymentBreakdown($detail['payment_breakdown'] ?? null);

        return [
            'enquiry_id' => (string) ($detail['enquiry_id'] ?? Str::uuid()),
            'service_id' => (string) $detail['service_id'],
            'amount' => is_numeric($detail['amount'] ?? null) ? (float) $detail['amount'] : 0,
            'currency' => $detail['currency'] ?? $defaultCurrency,
            'frequency' => in_array($detail['frequency'] ?? null, ['monthly', 'one_off'], true) ? $detail['frequency'] : 'one_off',
            'start_date' => $detail['start_date'] ?? null,
            'description' => $detail['description'] ?? '',
            'payment_breakdown' => $paymentBreakdown,
            'status' => (string) ($detail['status'] ?? 'active'),
            'service_tracking_type' => $trackingType,
            'show_on_leads_board' => $showOnLeadsBoard,
            'enquiry_status' => $status,
            'enquiry_created_at' => $detail['enquiry_created_at'] ?? null,
            'enquiry_updated_at' => $detail['enquiry_updated_at'] ?? null,
            'enquiry_meta' => is_array($detail['enquiry_meta'] ?? null) ? $detail['enquiry_meta'] : [],
            'xero_account_code' => $detail['xero_account_code'] ?? null,
            'project_service_id' => $detail['project_service_id'] ?? null,
        ];
    }

    public function syncServices(array $services, array $serviceDetails): array
    {
        $detailServices = collect($serviceDetails)
            ->pluck('service_id')
            ->filter()
            ->map(fn ($serviceId) => (string) $serviceId)
            ->all();

        return array_values(array_unique(array_merge(
            collect($services)->filter()->map(fn ($serviceId) => (string) $serviceId)->all(),
            $detailServices,
        )));
    }

    public function flattenProjectEnquiries(Project $project): Collection
    {
        $project->loadMissing(['client:id,name', 'clients:id,name', 'manager:id,name,email', 'admin:id,name,email']);

        $client = $project->client ?? $project->clients->first();
        $assignedTo = $project->manager ?? $project->admin;

        return collect($this->projectServiceDetails($project))
            ->filter(fn (array $detail) => $detail['service_tracking_type'] === self::TRACKING_ENQUIRY && $detail['show_on_leads_board'])
            ->map(function (array $detail) use ($project, $client, $assignedTo) {
                $createdAt = $detail['enquiry_created_at'] ?? $project->created_at;
                $updatedAt = $detail['enquiry_updated_at'] ?? $project->updated_at;
                $leadNumberSuffix = strtoupper(substr(str_replace('-', '', $detail['enquiry_id']), 0, 6));

                return [
                    'id' => $detail['enquiry_id'],
                    'enquiry_id' => $detail['enquiry_id'],
                    'card_type' => 'existing_client_enquiry',
                    'first_name' => null,
                    'last_name' => null,
                    'company' => $client?->name,
                    'title' => $detail['service_id'],
                    'email' => null,
                    'phone' => null,
                    'status' => $detail['enquiry_status'],
                    'source' => Arr::get($detail, 'enquiry_meta.source', 'existing_client_service'),
                    'lead_number' => 'EC'.$project->id.'-'.$leadNumberSuffix,
                    'assigned_to_id' => $assignedTo?->id,
                    'assigned_to' => $assignedTo ? [
                        'id' => $assignedTo->id,
                        'name' => $assignedTo->name,
                        'email' => $assignedTo->email,
                    ] : null,
                    'campaign' => null,
                    'metadata' => [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'client_id' => $client?->id,
                        'client_name' => $client?->name,
                        'service_name' => $detail['service_id'],
                        'service_tracking_type' => $detail['service_tracking_type'],
                        'show_on_leads_board' => $detail['show_on_leads_board'],
                        'enquiry_meta' => $detail['enquiry_meta'],
                        'amount' => $detail['amount'],
                        'currency' => $detail['currency'],
                        'frequency' => $detail['frequency'],
                        'start_date' => $detail['start_date'],
                    ],
                    'latest_context' => $detail['description'] ? ['summary' => $detail['description']] : null,
                    'description' => $detail['description'],
                    'service_name' => $detail['service_id'],
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'client_id' => $client?->id,
                    'client_name' => $client?->name,
                    'amount' => $detail['amount'],
                    'currency' => $detail['currency'],
                    'frequency' => $detail['frequency'],
                    'start_date' => $detail['start_date'],
                    'contacted_at' => null,
                    'last_communication_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            })
            ->values();
    }

    public function createEnquiry(Project $project, array $payload, User $user): array
    {
        $serviceDetails = $this->projectServiceDetails($project);
        $detail = $this->normalizeServiceDetail([
            'enquiry_id' => (string) Str::uuid(),
            'service_id' => $payload['service_id'],
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? $project->currency,
            'frequency' => $payload['frequency'] ?? 'one_off',
            'start_date' => $payload['start_date'] ?? null,
            'description' => $payload['description'] ?? '',
            'payment_breakdown' => $payload['payment_breakdown'] ?? [['label' => 'Payment 1', 'percentage' => 100, 'due_date' => null]],
            'service_tracking_type' => self::TRACKING_ENQUIRY,
            'show_on_leads_board' => $payload['show_on_leads_board'] ?? true,
            'enquiry_status' => $payload['enquiry_status'] ?? self::STATUS_PENDING_QUOTE,
            'enquiry_created_at' => now()->toDateTimeString(),
            'enquiry_updated_at' => now()->toDateTimeString(),
            'enquiry_meta' => [
                'created_by_id' => $user->id,
                'source' => $payload['source'] ?? 'existing_client_service',
            ],
        ], $project->currency ?? null);

        $serviceDetails[] = $detail;

        $this->saveProjectServiceDetails($project, $serviceDetails);

        return $detail;
    }

    public function updateEnquiry(Project $project, string $enquiryId, array $payload): array
    {
        $serviceDetails = $this->projectServiceDetails($project);
        $index = $this->findEnquiryIndex($serviceDetails, $enquiryId);

        if ($index === null) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'Existing client enquiry not found.',
            ]);
        }

        $detail = $serviceDetails[$index];
        $trackingType = $payload['service_tracking_type'] ?? $detail['service_tracking_type'];
        $showOnLeadsBoard = array_key_exists('show_on_leads_board', $payload) ? (bool) $payload['show_on_leads_board'] : $detail['show_on_leads_board'];
        $newStatus = array_key_exists('enquiry_status', $payload)
            ? $this->normalizeEnquiryStatus($payload['enquiry_status'])
            : $detail['enquiry_status'];

        $this->assertValidTransition($detail['enquiry_status'], $newStatus, $trackingType);

        $detail['service_id'] = $payload['service_id'] ?? $detail['service_id'];
        $detail['amount'] = array_key_exists('amount', $payload) && is_numeric($payload['amount']) ? (float) $payload['amount'] : $detail['amount'];
        $detail['currency'] = $payload['currency'] ?? $detail['currency'];
        $detail['frequency'] = $payload['frequency'] ?? $detail['frequency'];
        $detail['start_date'] = array_key_exists('start_date', $payload) ? $payload['start_date'] : $detail['start_date'];
        $detail['description'] = array_key_exists('description', $payload) ? (string) ($payload['description'] ?? '') : $detail['description'];
        $detail['payment_breakdown'] = array_key_exists('payment_breakdown', $payload)
            ? $this->normalizePaymentBreakdown($payload['payment_breakdown'])
            : $detail['payment_breakdown'];
        $detail['service_tracking_type'] = $trackingType;
        $detail['show_on_leads_board'] = $trackingType === self::TRACKING_ENQUIRY ? $showOnLeadsBoard : false;
        $detail['enquiry_status'] = $trackingType === self::TRACKING_ENQUIRY ? $newStatus : null;
        $detail['enquiry_updated_at'] = now()->toDateTimeString();

        $serviceDetails[$index] = $this->normalizeServiceDetail($detail, $project->currency ?? null);

        $this->saveProjectServiceDetails($project, $serviceDetails);

        return $serviceDetails[$index];
    }

    public function deleteEnquiry(Project $project, string $enquiryId): void
    {
        $serviceDetails = $this->projectServiceDetails($project);
        $index = $this->findEnquiryIndex($serviceDetails, $enquiryId);

        if ($index === null) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'Existing client enquiry not found.',
            ]);
        }

        unset($serviceDetails[$index]);
        $serviceDetails = array_values($serviceDetails);

        $this->saveProjectServiceDetails($project, $serviceDetails);
    }

    public function convertEnquiry(Project $project, string $enquiryId, array $payload, User $user): array
    {
        $serviceDetails = $this->projectServiceDetails($project);
        $index = $this->findEnquiryIndex($serviceDetails, $enquiryId);

        if ($index === null) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'Existing client enquiry not found.',
            ]);
        }

        $detail = $serviceDetails[$index];
        if ($detail['service_tracking_type'] !== self::TRACKING_ENQUIRY) {
            throw ValidationException::withMessages([
                'service_tracking_type' => 'Only enquiry-tracked services can be converted.',
            ]);
        }
        if ($detail['enquiry_status'] !== self::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'enquiry_status' => 'Only approved enquiries can be converted.',
            ]);
        }

        $conversionType = $payload['conversion_type'] ?? self::CONVERSION_TASK;
        if (! in_array($conversionType, [self::CONVERSION_TASK, self::CONVERSION_MILESTONE, self::CONVERSION_PROJECT], true)) {
            throw ValidationException::withMessages([
                'conversion_type' => 'Invalid conversion type.',
            ]);
        }

        $created = match ($conversionType) {
            self::CONVERSION_TASK => $this->createTaskFromEnquiry($project, $detail, $payload, $user),
            self::CONVERSION_MILESTONE => $this->createMilestoneFromEnquiry($project, $detail),
            self::CONVERSION_PROJECT => $this->createProjectFromEnquiry($project, $detail, $payload, $user),
        };

        $detail['enquiry_status'] = self::STATUS_CONVERTED;
        $detail['enquiry_updated_at'] = now()->toDateTimeString();
        $detail['enquiry_meta']['conversion'] = [
            'type' => $conversionType,
            'record_id' => $created['id'],
            'record_name' => $created['name'],
            'converted_at' => now()->toDateTimeString(),
            'converted_by_id' => $user->id,
        ];

        $serviceDetails[$index] = $this->normalizeServiceDetail($detail, $project->currency ?? null);

        $this->saveProjectServiceDetails($project, $serviceDetails);

        return [
            'detail' => $serviceDetails[$index],
            'created' => $created,
        ];
    }

    public function supportingData(Collection $projects): array
    {
        $projects = $projects->values();

        $clients = $projects
            ->flatMap(function (Project $project) {
                $items = collect();
                if ($project->client) {
                    $items->push($project->client);
                }
                return $items->merge($project->clients ?? collect());
            })
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);

        $projectItems = $projects
            ->sortBy('name')
            ->map(function (Project $project) {
                $clientIds = collect([$project->client?->id])
                    ->merge(($project->clients ?? collect())->pluck('id'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_ids' => $clientIds,
                    'primary_client_id' => $project->client?->id,
                    'client_name' => $project->client?->name ?? $project->clients?->first()?->name,
                ];
            })
            ->values();

        return [
            'clients' => $clients,
            'projects' => $projectItems,
        ];
    }

    protected function createTaskFromEnquiry(Project $project, array $detail, array $payload, User $user): array
    {
        $milestone = $project->supportMilestone();
        $taskType = TaskType::firstOrCreate(
            ['name' => 'New'],
            ['created_by_user_id' => $user->id]
        );
        $assignedToUserId = $payload['assigned_to_user_id'] ?? $project->project_manager_id ?? $user->id;

        $task = Task::create([
            'name' => $payload['name'] ?? $detail['service_id'],
            'description' => $detail['description'] ?: 'Created from existing client enquiry.',
            'assigned_to_user_id' => $assignedToUserId,
            'due_date' => $payload['due_date'] ?? $detail['start_date'],
            'status' => TaskStatus::ToDo->value,
            'task_type_id' => $taskType->id,
            'milestone_id' => $milestone->id,
            'creator_id' => $user->id,
            'creator_type' => get_class($user),
            'source' => 'existing_client_enquiry',
            'source_id' => $detail['enquiry_id'],
        ]);

        return [
            'type' => self::CONVERSION_TASK,
            'id' => $task->id,
            'name' => $task->name,
        ];
    }

    protected function createMilestoneFromEnquiry(Project $project, array $detail): array
    {
        $milestone = Milestone::create([
            'project_id' => $project->id,
            'name' => $detail['service_id'],
            'description' => $detail['description'] ?: 'Created from existing client enquiry.',
            'completion_date' => $detail['start_date'],
            'status' => MilestoneStatus::Pending->value,
        ]);

        return [
            'type' => self::CONVERSION_MILESTONE,
            'id' => $milestone->id,
            'name' => $milestone->name,
        ];
    }

    protected function createProjectFromEnquiry(Project $project, array $detail, array $payload, User $user): array
    {
        $clientId = $project->client?->id ?? $project->clients?->first()?->id;
        $newProjectServiceDetail = $this->normalizeServiceDetail([
            'service_id' => $detail['service_id'],
            'amount' => $detail['amount'],
            'currency' => $detail['currency'],
            'frequency' => $detail['frequency'],
            'start_date' => $detail['start_date'],
            'description' => $detail['description'],
            'payment_breakdown' => $detail['payment_breakdown'],
            'service_tracking_type' => self::TRACKING_OPERATIONAL,
            'show_on_leads_board' => false,
        ], $project->currency ?? null);

        $newProject = Project::create([
            'name' => $payload['name'] ?? trim($project->name.' - '.$detail['service_id']),
            'description' => $detail['description'] ?: $project->description,
            'client_id' => $clientId,
            'status' => ProjectStatus::Active->value,
            'project_type' => $project->project_type,
            'source' => 'existing_client_enquiry',
            'payment_type' => $detail['frequency'] === 'monthly' ? 'monthly' : 'one_off',
            'services' => [$detail['service_id']],
            'service_details' => [$newProjectServiceDetail],
            'project_manager_id' => $payload['assigned_to_user_id'] ?? $project->project_manager_id ?? $user->id,
            'project_admin_id' => $project->project_admin_id,
        ]);

        $this->saveProjectServiceDetails($newProject, [$newProjectServiceDetail]);

        if ($clientId) {
            $newProject->clients()->syncWithoutDetaching([$clientId => ['role_id' => null]]);
        }
        $newProject->users()->syncWithoutDetaching([$user->id => ['role_id' => 2]]);

        return [
            'type' => self::CONVERSION_PROJECT,
            'id' => $newProject->id,
            'name' => $newProject->name,
        ];
    }

    protected function normalizePaymentBreakdown($paymentBreakdown): array
    {
        if (is_array($paymentBreakdown) && array_is_list($paymentBreakdown) && count($paymentBreakdown) > 0) {
            return collect($paymentBreakdown)
                ->map(function ($payment, $index) {
                    return [
                        'label' => $payment['label'] ?? 'Payment '.($index + 1),
                        'percentage' => max(0, min(100, (int) ($payment['percentage'] ?? 0))),
                        'due_date' => $payment['due_date'] ?? null,
                    ];
                })
                ->values()
                ->all();
        }

        if (is_array($paymentBreakdown) && ! array_is_list($paymentBreakdown)) {
            return [
                ['label' => 'First', 'percentage' => (int) ($paymentBreakdown['first'] ?? 30), 'due_date' => null],
                ['label' => 'Second', 'percentage' => (int) ($paymentBreakdown['second'] ?? 30), 'due_date' => null],
                ['label' => 'Third', 'percentage' => (int) ($paymentBreakdown['third'] ?? 40), 'due_date' => null],
            ];
        }

        return [
            ['label' => 'Payment 1', 'percentage' => 100, 'due_date' => null],
        ];
    }

    protected function normalizeEnquiryStatus(?string $status): string
    {
        $status = strtolower((string) $status);
        if (! in_array($status, [self::STATUS_PENDING_QUOTE, self::STATUS_QUOTED, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CONVERTED], true)) {
            return self::STATUS_PENDING_QUOTE;
        }

        return $status;
    }

    protected function assertValidTransition(?string $currentStatus, ?string $newStatus, string $trackingType): void
    {
        if ($trackingType !== self::TRACKING_ENQUIRY || $newStatus === null || $currentStatus === null || $currentStatus === $newStatus) {
            return;
        }

        $allowed = [
            self::STATUS_PENDING_QUOTE => [self::STATUS_QUOTED, self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_QUOTED => [self::STATUS_PENDING_QUOTE, self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_APPROVED => [self::STATUS_QUOTED, self::STATUS_REJECTED, self::STATUS_CONVERTED],
            self::STATUS_REJECTED => [self::STATUS_PENDING_QUOTE, self::STATUS_QUOTED],
            self::STATUS_CONVERTED => [],
        ];

        if (! in_array($newStatus, $allowed[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'enquiry_status' => "Cannot transition enquiry from {$currentStatus} to {$newStatus}.",
            ]);
        }
    }

    protected function findEnquiryIndex(array $serviceDetails, string $enquiryId): ?int
    {
        foreach ($serviceDetails as $index => $detail) {
            if (($detail['enquiry_id'] ?? null) === $enquiryId) {
                return $index;
            }
        }

        return null;
    }

    public function projectServiceDetails(Project $project): array
    {
        $serviceRows = $project->projectServices()
            ->orderBy('id')
            ->get();

        if ($serviceRows->isNotEmpty()) {
            return $serviceRows
                ->map(function (ProjectService $service) use ($project) {
                    return $this->normalizeServiceDetail([
                        'project_service_id' => $service->id,
                        'project_service_id' => $service->id,
                        'enquiry_id' => $service->enquiry_id,
                        'service_id' => $service->service_id,
                        'amount' => $service->amount,
                        'currency' => $service->currency,
                        'frequency' => $service->frequency,
                        'start_date' => optional($service->start_date)->toDateString(),
                        'description' => $service->description,
                        'payment_breakdown' => $service->payment_breakdown,
                        'service_tracking_type' => $service->service_tracking_type,
                        'show_on_leads_board' => $service->show_on_leads_board,
                        'enquiry_status' => $service->enquiry_status,
                        'enquiry_created_at' => optional($service->enquiry_created_at)->toDateTimeString(),
                        'enquiry_updated_at' => optional($service->enquiry_updated_at)->toDateTimeString(),
                        'enquiry_meta' => $service->enquiry_meta,
                        'xero_account_code' => $service->xero_account_code,
                    ], $project->currency ?? null);
                })
                ->values()
                ->all();
        }

        return $this->normalizeServiceDetails($project->service_details, $project->currency ?? null);
    }

    public function saveProjectServiceDetails(Project $project, array $serviceDetails): array
    {
        $normalized = $this->normalizeServiceDetails($serviceDetails, $project->currency ?? null);

        DB::transaction(function () use ($project, $normalized): void {
            $existingByEnquiry = $project->projectServices()->get()->keyBy('enquiry_id');
            $incomingEnquiryIds = collect($normalized)->pluck('enquiry_id')->filter()->all();

            if ($incomingEnquiryIds === []) {
                $project->projectServices()->delete();
            } else {
                $project->projectServices()->whereNotIn('enquiry_id', $incomingEnquiryIds)->delete();
            }

            foreach ($normalized as $detail) {
                $existing = $existingByEnquiry->get($detail['enquiry_id'] ?? null);

                $payload = [
                    'project_id' => $project->id,
                    'enquiry_id' => $detail['enquiry_id'] ?? (string) Str::uuid(),
                    'service_id' => $detail['service_id'],
                    'description' => $detail['description'] ?? null,
                    'amount' => $detail['amount'] ?? 0,
                    'currency' => $detail['currency'] ?? $project->currency,
                    'frequency' => $detail['frequency'] ?? 'one_off',
                    'start_date' => $detail['start_date'] ?? null,
                    'payment_breakdown' => $detail['payment_breakdown'] ?? null,
                    'status' => $detail['status'] ?? 'active',
                    'service_tracking_type' => $detail['service_tracking_type'] ?? self::TRACKING_OPERATIONAL,
                    'show_on_leads_board' => (bool) ($detail['show_on_leads_board'] ?? false),
                    'enquiry_status' => $detail['enquiry_status'] ?? null,
                    'enquiry_created_at' => $detail['enquiry_created_at'] ?? null,
                    'enquiry_updated_at' => $detail['enquiry_updated_at'] ?? null,
                    'enquiry_meta' => is_array($detail['enquiry_meta'] ?? null) ? $detail['enquiry_meta'] : [],
                    'xero_account_code' => $detail['xero_account_code'] ?? ($existing?->xero_account_code),
                ];

                if ($existing) {
                    $existing->update($payload);
                } else {
                    ProjectService::query()->create($payload);
                }
            }

            $project->services = $this->syncServices($project->services ?? [], $normalized);
            // Keep the legacy JSON column in sync during phase-1 rollout.
            $project->service_details = $normalized;
            $project->save();
        });

        return $normalized;
    }
}