<?php

namespace App\Services;

use App\Actions\Points\AwardEmailPointsAction;
use App\Actions\Points\AwardKudosPointsAction;
use App\Actions\Points\AwardMilestonePointsAction;
use App\Actions\Points\AwardStandupPointsAction;
use App\Actions\Points\AwardTaskPointsAction;
use App\Models\Email;
use App\Models\Kudo;
use App\Models\Milestone;
use App\Models\PointsLedger;
use App\Models\ProjectNote;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class PointsService
{
    /**
     * Awards points for a given model based on its type.
     *
     * @param  object  $pointableObject  The model object to check for points.
     * @return PointsLedger|null The newly created PointsLedger entry, or null if no points were awarded.
     */
    public function awardPointsFor(object $pointableObject): ?PointsLedger
    {
        // Map the model class to its corresponding action class.
        $actionClass = match (get_class($pointableObject)) {
            ProjectNote::class => AwardStandupPointsAction::class,
            Task::class => AwardTaskPointsAction::class,
            Milestone::class => AwardMilestonePointsAction::class,
            Kudo::class => AwardKudosPointsAction::class,
            Email::class => AwardEmailPointsAction::class,
            default => null,
        };

        // If no action is defined for this model, log and exit.
        if (is_null($actionClass)) {
            Log::warning('PointsService was called with a non-pointable model: '.get_class($pointableObject));

            return null;
        }

        // Resolve the action from the service container and execute it.
        $action = resolve($actionClass);

        return $action->execute($pointableObject);
    }
}
