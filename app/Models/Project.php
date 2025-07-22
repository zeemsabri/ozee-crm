<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

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
}
