<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadIntakeSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public array $payload;

    /**
     * Create a new message instance.
     */
    public function __construct(Lead $lead, array $payload = [])
    {
        $this->lead = $lead;
        $this->payload = $payload;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Lead Submitted: '.$this->lead->full_name ?: $this->lead->email)
            ->markdown('emails.leads.intake_submitted', [
                'lead' => $this->lead,
                'payload' => $this->payload,
            ]);
    }
}
