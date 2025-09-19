<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Models\Traits\Taggable;
use Illuminate\Support\Facades\Cache;
use App\Models\CurrencyRate;
use App\Models\FileAttachment;

class Project extends Model
{
    use HasFactory, Taggable, SoftDeletes;

    protected $appends = [
        'logo_url',
    ];

    const LEADS = 'Leads';
    const MANAGEMENT = 'Management';
    const DEVELOPMENT = 'Development';

    protected $fillable = [
        'name',
        'description',
        'website',
        'social_media_link',
        'preferred_keywords',
        'reporting_sites',
        'google_chat_id',
        'client_id',
        'project_manager_id',
        'project_admin_id',
        'status',
        'project_type',
        'services',
        'service_details',
        'source',
        'total_amount',
        'contract_details',
        'google_drive_link',
        'google_drive_folder_id',
        'payment_type',
        'logo',
        'logo_google_drive_file_id',
        'documents',
        'timezone',
        'project_tier_id',
        'profit_margin_percentage',
        'last_email_sent',
        'last_email_received',
    ];

    protected $casts = [
        'status' => \App\Enums\ProjectStatus::class,
        'services' => 'array',
        'service_details' => 'array',
        'total_amount' => 'decimal:2',
        'payment_type' => 'string',
        'documents' => 'array',
        'last_email_sent' => 'datetime',
        'last_email_received' => 'datetime',
    ];

    protected $hidden = [
        'profit_margin_percentage',
    ];

    const SUPPORT = 'support';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }

    public function getLogoUrlAttribute(): ?string
    {
        try {
            $file = $this->files()
                ->whereIn('mime_type', ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'])
                ->latest()
                ->first();
            if (!$file) return null;
            return $file->thumbnail_url ?: $file->path_url;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'project_client')->withPivot('role_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user')->withPivot('role_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'project_admin_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function notes()
    {
        return $this->hasMany(ProjectNote::class);
    }

    /**
     * Get the milestones for this project.
     */
    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function supportMilestone()
    {
        $supportMilestone = $this->milestones()->where('name', self::SUPPORT);

        if($supportMilestone->exists()) {
            return $supportMilestone->first();
        }

        return $this->milestones()->create([
            'name'  =>  self::SUPPORT,
            'description'   =>  'Support milestone for tickets created by clients',
            'status'    =>  \App\Enums\MilestoneStatus::InProgress
        ]);
    }

    public function wireframes()
    {
        return $this->hasMany(Wireframe::class);
    }

    /**
     * Get the meetings for this project.
     */
    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Get the resources for this project.
     */
    public function resources()
    {
        return $this->morphMany(Resource::class, 'resourceable');
    }

    /**
     * Get the documents for this project.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Context records where this project is the subject (linkable).
     */
    public function contexts()
    {
        return $this->hasMany(Context::class);
    }

    /**
     * Get the expendable directly associated with this project.
     */
    public function expendable()
    {
        return $this->morphMany(ProjectExpendable::class, 'expendable');
    }


    public function budget()
    {
        return $this->expendable()->whereNull('user_id');
    }

    /**
     * Get the bonus configuration groups associated with this project.
     */
    public function bonusConfigurationGroups()
    {
        return $this->belongsToMany(BonusConfigurationGroup::class, 'project_bonus_configuration_group', 'project_id', 'group_id')
            ->withTimestamps();
    }

    /**
     * Get all active bonus configurations for this project.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveBonusConfigurations()
    {
        $configs = collect();

        $groups = $this->bonusConfigurationGroups()->where('is_active', true)->get();

        foreach ($groups as $group) {
            $groupConfigs = $group->bonusConfigurations()
                ->where('isActive', true)
                ->get();

            $configs = $configs->merge($groupConfigs);
        }

        return $configs->unique('id');
    }

    /**
     * Get all active bonus configurations for a specific source type.
     *
     * @param string $sourceType The source type (standup, task, milestone, etc.)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBonusConfigurationsForSourceType($sourceType)
    {
        $configs = collect();

        $groups = $this->bonusConfigurationGroups()->where('is_active', true)->get();

        foreach ($groups as $group) {
            $groupConfigs = $group->bonusConfigurations()
                ->where('isActive', true)
                ->where('appliesTo', $sourceType)
                ->get();

            $configs = $configs->merge($groupConfigs);
        }

        return $configs->unique('id');
    }

    /**
     * Get all active bonus configurations of a specific type.
     *
     * @param string $type The configuration type (bonus/penalty)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBonusConfigurationsByType($type)
    {
        $configs = collect();

        $groups = $this->bonusConfigurationGroups()->where('is_active', true)->get();

        foreach ($groups as $group) {
            $groupConfigs = $group->bonusConfigurations()
                ->where('isActive', true)
                ->where('type', $type)
                ->get();

            $configs = $configs->merge($groupConfigs);
        }

        return $configs->unique('id');
    }

    /**
     * Get all bonus transactions for this project.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bonusTransactions()
    {
        return $this->hasMany(BonusTransaction::class);
    }

    /**
     * Get a summary of bonus/penalty transactions for this project.
     *
     * @param \DateTime|null $startDate Optional start date for filtering
     * @param \DateTime|null $endDate Optional end date for filtering
     * @return array The summary data
     */
    public function getBonusSummary(?\DateTime $startDate = null, ?\DateTime $endDate = null)
    {
        $query = $this->bonusTransactions()
            ->where('status', \App\Enums\BonusTransactionStatus::Processed->value);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        $transactions = $query->get();

        $bonusTransactions = $transactions->where('type', 'bonus');
        $penaltyTransactions = $transactions->where('type', 'penalty');

        $totalBonus = $bonusTransactions->sum('amount');
        $totalPenalty = $penaltyTransactions->sum('amount');
        $netBonus = $totalBonus - $totalPenalty;

        // Group by user
        $userSummaries = [];
        foreach ($transactions as $transaction) {
            $userId = $transaction->user_id;
            if (!isset($userSummaries[$userId])) {
                $userSummaries[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $transaction->user->name,
                    'total_bonus' => 0,
                    'total_penalty' => 0,
                    'net_bonus' => 0,
                    'bonus_count' => 0,
                    'penalty_count' => 0,
                ];
            }

            if ($transaction->type === 'bonus') {
                $userSummaries[$userId]['total_bonus'] += $transaction->amount;
                $userSummaries[$userId]['bonus_count']++;
            } else {
                $userSummaries[$userId]['total_penalty'] += $transaction->amount;
                $userSummaries[$userId]['penalty_count']++;
            }

            $userSummaries[$userId]['net_bonus'] = $userSummaries[$userId]['total_bonus'] - $userSummaries[$userId]['total_penalty'];
        }

        return [
            'total_bonus' => $totalBonus,
            'total_penalty' => $totalPenalty,
            'net_bonus' => $netBonus,
            'bonus_count' => $bonusTransactions->count(),
            'penalty_count' => $penaltyTransactions->count(),
            'user_summaries' => array_values($userSummaries),
            'transactions' => $transactions,
        ];
    }
    /**
     * Upload documents to the project.
     *
     * @param array $files Array of uploaded files
     * @param \App\Services\GoogleDriveService $googleDriveService
     * @return array Array of created Document models
     */
    public function uploadDocuments(
        array $files,
        $googleDriveService, null|string
        $field = 'webContentLink',
        $subFolder = null,
        $createRecord = true
    )
    {
        $uploadedDocuments = [];

        foreach ($files as $file) {
//            $localPath = $file->store('documents', 'public');
            $localPath = Storage::disk('public')->putFile('documents', $file);
            $fullLocalPath = Storage::disk('public')->path($localPath);
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            $documentData = [
                'project_id' => $this->id,
                'path' => $localPath,
                'filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_size' => $fileSize
            ];

            try {
                if ($folder = $this->google_drive_folder_id) {

                    if($subFolder) {
                        $folder = $subFolder;
                    }

                    $response = $googleDriveService->uploadFile($fullLocalPath, $originalFilename, $folder, $field);
                    $documentData['google_drive_file_id'] = $response['id'] ?? null;
                    $documentData['path'] = $response['path'] ?? null;
                    $documentData['thumbnail'] = $response['thumbnail'] ?? null;
                }

                Storage::disk('public')->delete($localPath);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to upload file to Google Drive: ' . $e->getMessage(), ['project_id' => $this->id, 'file_name' => $originalFilename]);
                $documentData['upload_error'] = 'Failed to upload to Google Drive';
            }

            if($createRecord) {
                $document = \App\Models\Document::create($documentData);
                $uploadedDocuments[] = $document;
            }
            else {
                $uploadedDocuments[] = $documentData;
            }

        }

        return $uploadedDocuments;
    }

    public function getLogoAttribute($value)
    {
        return $this->attributes['logo'] = Storage::url($value);
    }

    // app/Models/Project.php
    public function deliverables()
    {
        return $this->hasMany(Deliverable::class);
    }

    /**
     * Get the project deliverables for this project.
     */
    public function projectDeliverables()
    {
        return $this->hasMany(ProjectDeliverable::class);
    }

    /**
     * Get the tier associated with the project.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tier()
    {
        return $this->belongsTo(ProjectTier::class, 'project_tier_id');
    }

    /**
     * Get the points ledger entries for the project.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function points()
    {
        return $this->hasMany(PointsLedger::class);
    }

    // ---- Expendables computed attributes ----
    /**
     * Convert currency using DB-backed rates (rate_to_usd), cached for 24h.
     */
    protected function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);
        if ($from === $to) {
            return round($amount, 2);
        }
        $rates = Cache::remember('currency_rates_to_usd', 60 * 24, function () {
            $dbRates = CurrencyRate::all()->pluck('rate_to_usd', 'currency_code')->toArray();
            if (!isset($dbRates['USD'])) {
                $dbRates['USD'] = 1.0;
            }
            return $dbRates;
        });
        $fromRate = $rates[$from] ?? null;
        $toRate = $rates[$to] ?? null;
        if (!$fromRate || !$toRate) {
            return round($amount, 2);
        }
        $amountInUSD = $amount * (float) $fromRate;
        $converted = $amountInUSD / (float) $toRate;
        return round($converted, 2);
    }

    public function getTotalBudgetAttribute()
    {
        $convertTo = 'AUD';
        $total = 0.0;
        foreach ($this->budget()->get() as $item) {
            $total += $this->convertCurrency((float) $item->amount, (string) $item->currency, $convertTo);
        }
        return round($total, 2);
    }


    public function getTotalAssignedMilestoneAmountAttribute()
    {
        //Calculate total amount which is assigned to each milestone

        $convertTo = 'AUD';
        $milestones = $this->milestones()->with('budget')->get();

        $total = 0.0;
        foreach($milestones as $milestone) {

            if($budget = $milestone->budget) {
                $total += $this->convertCurrency((float) $budget->amount, (string) $budget->currency, $convertTo);
            }

        }

        return round($total, 2);

    }

    public function milestoneContracts()
    {
        //Return all contracts within each milestones
        $milestones = $this->milestones()->with('expendable')->get();
        $expendables = collect();
        foreach ($milestones as $milestone) {
            $expendables = $expendables->merge($milestone->expendable);
        }
        return $expendables;
    }

    public function getPendingContractsAmountAttribute()
    {

        //From all contracts calculate all pending contract amounts
        $convertTo = 'AUD';
        $allContracts = $this->milestoneContracts()->where('status', ProjectExpendable::STATUS_PENDING);

        $total = 0.0;
        foreach($allContracts as $contract) {
            $total += $this->convertCurrency((float) $contract->amount, (string) $contract->currency, $convertTo);
        }

        return round($total, 2);
    }

    public function getApprovedContractsAmountAttribute()
    {

        //From all contracts calculate all approved contract amounts
        $convertTo = 'AUD';
        $allContracts = $this->milestoneContracts()->where('status', ProjectExpendable::STATUS_ACCEPTED);

        $total = 0.0;
        foreach($allContracts as $contract) {
            $total += $this->convertCurrency((float) $contract->amount, (string) $contract->currency, $convertTo);
        }

        return round($total, 2);

    }

    public function getAvailableForNewMilestonesAttribute()
    {
        return round($this->total_budget - $this->total_assigned_milestone_amount, 2);
    }

    /**
     * Total of approved milestone expendables (user-bound) in the project's currency.
     */
    public function getApprovedMilestoneExpendablesTotalAttribute(): float
    {
        return round(($this->total_budget - $this->approved_contracts_amount), 2);
    }

    /**
     * Remaining spendables = project total_expendable_amount - approved milestone expendables (user-bound), in project currency.
     */
    public function getRemainingSpendablesAttribute(): float
    {
        return round(($this->total_budget - $this->approved_contracts_amount), 2);
    }

}
