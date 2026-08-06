<?php

namespace App\Mail;

use App\Models\ProjectExpendable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProposalRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProjectExpendable $proposal,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        $projectName = $this->proposal->project?->name ?: 'Project';
        return new Envelope(
            subject: "Proposal Status Update: {$this->proposal->name} - {$projectName}",
        );
    }

    public function content(): Content
    {
        $project = $this->proposal->project;
        if ($project && empty($project->public_share_token)) {
            $project->public_share_token = Str::random(64);
            $project->public_share_enabled = true;
            $project->save();
        }

        $shareUrl = null;
        if ($project && $project->public_share_token) {
            $shareUrl = route('public.projects.pretty', [
                'slug' => Str::slug($project->name ?: 'project'),
                'code' => substr((string) $project->public_share_token, 0, 12),
            ]);
        }

        return new Content(
            view: 'emails.proposal-rejected',
            with: [
                'proposal' => $this->proposal,
                'project'  => $project,
                'user'     => $this->proposal->user,
                'reason'   => $this->reason,
                'shareUrl' => $shareUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
