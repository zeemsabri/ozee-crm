<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LiveStatusController extends Controller
{
    /**
     * Display the live status of all users.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $users = User::with(['activeTask.milestone.project'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_online' => $user->is_online,
                    'online_since' => $user->online_data['last_status_change'] ?? null,
                    'timezone' => $user->timezone ?? 'UTC',
                    'active_task' => $user->activeTask ? [
                        'id' => $user->activeTask->id,
                        'name' => $user->activeTask->name,
                        'project_name' => $user->activeTask->milestone?->project?->name ?? 'N/A',
                    ] : null,
                    'avatar' => $user->avatar,
                ];
            });

        return Inertia::render('Admin/LiveStatus/Index', [
            'users' => $users
        ]);
    }

    /**
     * Get the online/offline logs for a specific user.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function logs(Request $request, User $user)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $logs = $user->onlineActivityLogs($startDate, $endDate);

        return response()->json([
            'logs' => $logs
        ]);
    }
}
