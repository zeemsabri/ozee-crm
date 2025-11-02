<?php

namespace App\Services;

use App\Models\MonthlyPoint;
use App\Models\PointsLedger;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LedgerService
{
    /**
     * Records a new point transaction and updates the user's monthly total.
     *
     * @param  User  $user  The user receiving the points.
     * @param  float|int  $points  The final calculated points amount.
     * @param  string  $description  A description of the transaction.
     * @param  string  $status  The status of the transaction (e.g., 'paid', 'denied').
     * @param  Model|null  $pointable  The model that triggered the points (e.g., Task, Milestone).
     * @param  Project|null  $project  The project associated with the transaction.
     * @param  Carbon|null  $transactionDate  The date of the transaction. Defaults to now().
     * @return PointsLedger The newly created PointsLedger model instance.
     */
    public function record(
        User $user,
        float|int $points,
        string $description,
        string $status,
        ?Model $pointable = null,
        ?Project $project = null,
        ?Carbon $transactionDate = null
    ): PointsLedger {
        // Use the provided date or default to the current time.
        $date = $transactionDate ?? $pointable?->created_at ?? now();

        // Rule: If the status is 'denied', the awarded points must be 0.
        $pointsAwarded = ($status === 'denied') ? 0 : $points;

        // Create and save the new PointsLedger entry.
        $ledgerEntry = PointsLedger::create([
            'user_id' => $user->id,
            'points_awarded' => $pointsAwarded,
            'description' => $description,
            'status' => $status,
            'pointable_id' => $pointable ? $pointable->id : null,
            'pointable_type' => $pointable ? get_class($pointable) : null,
            'project_id' => $project ? $project->id : 2,
            'created_at' => $date,
        ]);

        // If the status is not 'denied', update the monthly points total.
        if ($status !== 'denied') {
            // Find or create the MonthlyPoint record for the given month and year.
            $monthlyPoints = MonthlyPoint::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => $date->year,
                    'month' => $date->month,
                ],
                [
                    'total_points' => 0, // Initialize total points to 0 if the record is new
                ]
            );

            // Increment the total_points for the monthly record.
            $monthlyPoints->increment('total_points', $points);
        }

        // Return the newly created ledger entry.
        return $ledgerEntry;
    }
}
