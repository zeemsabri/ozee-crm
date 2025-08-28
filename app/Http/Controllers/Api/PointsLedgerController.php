<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointsLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PointsLedgerController extends Controller
{
    /**
     * Return the authenticated user's points ledger for a given month/year (defaults to current month).
     * Query params: year, month, page, per_page, pointable_type, project_id, user_id (requires manage_projects)
     */
    public function mine(Request $request)
    {
        $authUser = Auth::user();
        $userTimezone = $authUser->timezone ?? 'Asia/Karachi';

        $now = Carbon::now($userTimezone);
        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $perPage = (int) $request->query('per_page', 20);

        // Convert start and end of month from user's timezone to UTC for the database query
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $userTimezone)->setTimezone('UTC');
        $end = (clone $start)->addMonth()->subSecond()->setTimezone('UTC');

        $query = PointsLedger::with(['project:id,name'])
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at');

        // By default, restrict to current user
        $filterUserId = $authUser->id;
        // If manage_projects permission and user_id provided, allow filtering by other users
        if ($request->filled('user_id') && $authUser->can('manage_projects')) {
            $filterUserId = (int) $request->query('user_id');
        }
        $query->where('user_id', $filterUserId);

        // Optional filter: project
        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->query('project_id'));
        }

        // Optional filter: pointable_type by model short name (e.g., Task, Kudo, ProjectNote)
        if ($request->filled('pointable_type')) {
            $short = trim($request->query('pointable_type'));
            // Convert short model name to FQN if it exists in our Models namespace
            $fqn = "App\\Models\\" . ltrim($short, '\\');
            // Also accept already-FQN strings
            $query->where(function ($q) use ($fqn, $short) {
                $q->where('pointable_type', $fqn)
                    ->orWhere('pointable_type', $short);
            });
        }

        if($request->filled('status')){
            $query->where('status', $request->query('status'));
        }

        $paginator = $query->paginate($perPage);

        // Shape the data minimally for the UI consumption
        $data = collect($paginator->items())->map(function (PointsLedger $row) use ($userTimezone) {
            return [
                'id' => $row->id,
                'created_at' => $row->created_at?->setTimezone($userTimezone)->toDateTimeString(),
                'date' => $row->created_at?->toDateString(),
                'points_awarded' => (float) $row->points_awarded,
                'description' => $row->description,
                'status' => $row->status,
                'project' => $row->project ? [
                    'id' => $row->project->id,
                    'name' => $row->project->name,
                ] : null,
                'meta' => $row->meta,
            ];
        });

        return response()->json([
            'year' => $year,
            'month' => $month,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
