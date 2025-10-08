<?php

namespace App\Listeners;

use App\Events\WorkflowTriggerEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GlobalModelEventSubscriber
{
    public function subscribe($events): void
    {

        $enabled = (bool) config('automation.global_model_events.enabled', true);
        if (!$enabled) {
            return;
        }

        $verbs = self::getVerbs();
        $verbs = array_map('strtolower', $verbs);

        if (in_array('created', $verbs, true)) {
            $events->listen('eloquent.created: *', function ($event, array $payload) {
                $this->handleModelEvent($payload[0], 'created');
            });
        }
        if (in_array('updated', $verbs, true)) {
            $events->listen('eloquent.updated: *', function ($event, array $payload) {
                $this->handleModelEvent($payload[0], 'updated');
            });
        }
        if (in_array('saved', $verbs, true)) {
            $events->listen('eloquent.saved: *', function ($event, array $payload) {
                $this->handleModelEvent($payload[0], 'saved');
            });
        }
        if (in_array('deleted', $verbs, true)) {
            $events->listen('eloquent.deleted: *', function ($event, array $payload) {
                $this->handleModelEvent($payload[0], 'deleted');
            });
        }
    }

    public static function getVerbs()
    {
        return $verbs = config('automation.global_model_events.verbs', ['created', 'updated']);
    }

    public static function dispatch($model, string $verb = 'updated', $from = 'dispatch')
    {
        $self = new self();
        $self->handleModelEvent($model, $verb, $from);
    }

    protected function handleModelEvent($model, string $verb, $from = null): void
    {
        // Skip if handler-created update
        if (property_exists($model, '__automation_suppressed') && $model->__automation_suppressed) {
            return;
        }

        $base = class_basename($model);
        $allow = config('automation.models.allow', []);
        $deny  = config('automation.models.deny', ['ExecutionLog']);

        if (in_array($base, $deny, true)) return;
        if (!empty($allow) && !in_array($base, $allow, true)) return;

        $eventName = strtolower($base) . '.' . strtolower($verb);

        $context = [
            strtolower($base) => method_exists($model, 'toArray') ? $model->toArray() : [],
        ];

        // Optionally attach user
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $context['user'] = [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // ignore auth errors in console/jobs
        }

        event(new WorkflowTriggerEvent($eventName, $context, (string) $model->getKey(), $from));
    }
}
