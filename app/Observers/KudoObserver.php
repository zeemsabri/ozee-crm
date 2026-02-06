<?php

namespace App\Observers;

use App\Events\KudoApprovedEvent;
use App\Models\Kudo;
use Illuminate\Support\Facades\Log;

class KudoObserver
{
    /**
     * Handle the Kudo "updated" event.
     */
    public function updated(Kudo $kudo): void
    {
        // Only dispatch when the approval flag has just been set to true
        if ($kudo->isDirty('is_approved')) {
            $wasApproved = (bool) $kudo->getOriginal('is_approved');
            $isApproved = (bool) $kudo->is_approved;

            if (! $wasApproved && $isApproved) {
                KudoApprovedEvent::dispatch($kudo);
            }
        }
    }
}
