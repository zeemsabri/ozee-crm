<?php

namespace App\Mail;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the accounts inbox that a supplier has uploaded a bill through a public
 * project share link.
 *
 * Nothing notified anyone on bill creation before this — internally created bills only
 * surfaced via the pending-approvals badge. A guest bill needs a person to pick it up
 * and fill in the Xero account code and transaction type, so it gets a real nudge.
 */
class GuestBillSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Bill $bill) {}

    public function envelope(): Envelope
    {
        $project = $this->bill->project?->name ?: 'Project';
        $reference = $this->bill->reference_number ?: $this->bill->bill_number;

        return new Envelope(
            subject: "Supplier bill uploaded: {$reference} — {$project}",
        );
    }

    public function content(): Content
    {
        // Guests can be soft-deleted while still holding a valid session (OtpService
        // resolves them withTrashed), and Bill::contractor() has no withTrashed — so
        // without this the relation comes back null and the view breaks.
        $this->bill->loadMissing([
            'project',
            'expendable',
            'contractor' => fn ($q) => $q->withTrashed(),
        ]);

        return new Content(
            view: 'emails.guest-bill-submitted',
            with: [
                'bill' => $this->bill,
                'project' => $this->bill->project,
                'contractor' => $this->bill->contractor,
                'expendable' => $this->bill->expendable,
                // Deep link to the admin bills screen, where the Xero fields are filled in.
                'billsUrl' => url('/admin/financials/bills'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
