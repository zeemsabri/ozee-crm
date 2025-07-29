<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Traits\Taggable;

class Project extends Model
{
    use HasFactory, Taggable;

    protected $fillable = [
        'name',
        'description',
        'website',
        'social_media_link',
        'preferred_keywords',
        'google_chat_id',
        'client_id',
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
    ];

    protected $casts = [
        'status' => 'string',
        'services' => 'array',
        'service_details' => 'array',
        'total_amount' => 'decimal:2',
        'payment_type' => 'string',
        'documents' => 'array',
    ];

    const SUPPORT = 'support';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'project_client')->withPivot('role_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user')->withPivot('role_id');
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
            'status'    =>  'In Progress'
        ]);
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
            ->where('status', 'processed');

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
    public function uploadDocuments(array $files, $googleDriveService)
    {
        $uploadedDocuments = [];

        foreach ($files as $file) {
            $localPath = $file->store('documents', 'public');
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
                if ($this->google_drive_folder_id) {
                    $response = $googleDriveService->uploadFile($fullLocalPath, $originalFilename, $this->google_drive_folder_id);
                    $documentData['google_drive_file_id'] = $response['id'] ?? null;
                    $documentData['path'] = $response['path'] ?? null;
                    $documentData['thumbnail'] = $response['thumbnail'] ?? null;
                }

                Storage::disk('public')->delete($localPath);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to upload file to Google Drive: ' . $e->getMessage(), ['project_id' => $this->id, 'file_name' => $originalFilename]);
                $documentData['upload_error'] = 'Failed to upload to Google Drive';
            }

            $document = \App\Models\Document::create($documentData);
            $uploadedDocuments[] = $document;
        }

        return $uploadedDocuments;
    }
}
