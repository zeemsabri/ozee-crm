<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProcessBasicProperty
{
    /**
     * Handle an incoming request.
     *
     * Usage: middleware('process.basic:field,ModelFQN')
     * - field: the incoming request field name which may contain an ID or a string like 'new_name_timestamp'
     * - ModelFQN: fully qualified class name of the model to create (must have 'name' and 'slug' columns)
     */
    public function handle(Request $request, Closure $next, string $field, string $modelFqn): Response
    {
        // If the request already has a numeric *_id, leave it as-is
        $incoming = $request->input($field) ?? $request->input($field.'_id');

        if ($incoming === null) {
            return $next($request);
        }

        // Normalize to *_id on the request payload
        $targetKey = str_ends_with($field, '_id') ? $field : $field.'_id';

        if (is_numeric($incoming)) {
            $request->merge([$targetKey => (int) $incoming]);

            return $next($request);
        }

        // Handle 'new_...' format
        if (is_string($incoming) && str_starts_with($incoming, 'new_')) {
            // Extract label between 'new_' and optional timestamp suffix
            $parts = explode('_', $incoming);
            array_shift($parts); // remove 'new'
            // If last part is a timestamp (all digits and long), drop it
            $last = end($parts);
            if ($last !== false && ctype_digit($last) && strlen($last) >= 10) {
                array_pop($parts);
            }
            $raw = implode(' ', $parts);
            $label = trim(str_replace('-', ' ', $raw));
            $name = preg_replace('/\s+/', ' ', $label);
            $slug = Str::slug($name);

            if (class_exists($modelFqn)) {
                $model = $modelFqn::firstOrCreate(['slug' => $slug], ['name' => $name]);
                $request->merge([$targetKey => $model->id]);
            }
        }

        return $next($request);
    }
}
