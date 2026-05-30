<?php

namespace App\Mail;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProjectInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $recipientName,
        public string $recipientEmail,
        public ?string $customMessage = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Project Invite] '.$this->project->name,
        );
    }

    public function content(): Content
    {
        $shareUrl       = route('public.projects.pretty', [
            'slug' => Str::slug($this->project->name ?: 'project'),
            'code' => substr((string) $this->project->public_share_token, 0, 12),
        ]);
        $trackingUrl    = route('project.track', ['id' => $this->project->id, 'email' => $this->recipientEmail]);

        return new Content(
            view: 'emails.project-invite',
            with: [
                'project'       => $this->project,
                'recipientName' => $this->recipientName,
                'shareUrl'      => $shareUrl,
                'customMessage' => $this->customMessage,
                'trackingUrl'   => $trackingUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
