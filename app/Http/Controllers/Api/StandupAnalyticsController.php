<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use App\Models\UserAvailability;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StandupAnalyticsController extends Controller
{
    /**
     * Resolve a date range based on the requested range mode.
     *
     * Supported range values:
     * - today
     * - yesterday
     * - 7, 14, 30 (last N days, inclusive)
     * - custom (requires start_date and end_date, Y-m-d)
     */
    private function resolveDateRange(Request $request, int $defaultDays = 7): array
    {
        $range = (string) $request->input('range', (string) $defaultDays);
        $today = Carbon::today();

        $makeSpan = static function (Carbon $start, Carbon $end): array {
            // Always normalise to full days
            return [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ];
        };

        switch ($range) {
            case 'today':
                return $makeSpan($today, $today);
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                return $makeSpan($yesterday, $yesterday);
            case '7':
            case '14':
            case '30':
                $days = (int) $range;
                if ($days > 0) {
                    $start = $today->copy()->subDays($days - 1);
                    return $makeSpan($start, $today);
                }
                break;
            case 'custom':
                $startStr = $request->input('start_date');
                $endStr = $request->input('end_date');
                if ($startStr && $endStr) {
                    try {
                        $start = Carbon::parse($startStr);
                        $end = Carbon::parse($endStr);
                        return $makeSpan($start, $end);
                    } catch (\Throwable $e) {
                        // fall through to default
                    }
                }
                break;
            default:
                if (is_numeric($range)) {
                    $days = (int) $range;
                    if ($days > 0) {
                        $start = $today->copy()->subDays($days - 1);
                        return $makeSpan($start, $today);
                    }
                }
        }

        // Fallback to default last N days
        $start = $today->copy()->subDays($defaultDays - 1);
        return $makeSpan($start, $today);
    }

    /**
     * Get initial data for filters (Projects, Users).
     */
    public function getFilters()
    {
        $projects = Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        $users = User::select('id', 'name', 'chat_name')
            ->whereHas('role', function ($q) {
                $q->whereIn('slug', ['employee', 'manager', 'admin', 'super-admin']);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name
                ];
            });

        return response()->json([
            'projects' => $projects,
            'users' => $users,
        ]);
    }

    /**
     * Get the Compliance Matrix data.
     */
    public function getComplianceMatrix(Request $request)
    {
        $projectId = $request->input('project_id');
        $userId = $request->input('user_id');

        [$startDateTime, $endDateTime] = $this->resolveDateRange($request, 7);
        // Use date-only for the matrix headers / availability query
        $startDate = $startDateTime->copy()->startOfDay();
        $endDate = $endDateTime->copy()->startOfDay();
        $period = CarbonPeriod::create($startDate, $endDate);

        // Base query for users
        $usersQuery = User::query()
            ->whereHas('role', function ($q) {
                $q->whereIn('slug', ['employee', 'manager', 'admin', 'super-admin']);
            });

        if ($userId && $userId !== 'all') {
            $usersQuery->where('id', $userId);
        }

        $users = $usersQuery->get();

        // Pre-fetch availabilities for the date range
        $availabilities = UserAvailability::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy('user_id');

        // Pre-fetch standups for the date range
        $standupsQuery = ProjectNote::where('type', ProjectNote::STANDUP)
            ->whereBetween('created_at', [$startDateTime, $endDateTime]);

        if ($projectId && $projectId !== 'all') {
            $standupsQuery->where('project_id', $projectId);
        }

        $standups = $standupsQuery->get()
            ->groupBy(function ($item) {
                return $item->creator_id . '_' . $item->created_at->format('Y-m-d');
            });

        $matrix = [];

        foreach ($users as $user) {
            $userRow = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random&color=fff',
                'days' => []
            ];

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = $date->isWeekend();

                // Check explicit availability (Leave/Time off)
                $userAvail = $availabilities->get($user->id);
                $isLeave = $userAvail && $userAvail->where('date', $date)->where('is_available', false)->first();

                // Check for standup submission
                $key = $user->id . '_' . $dateStr;
                $submission = $standups->get($key)?->first();

                $status = 'missed';
                $meta = null;

                if ($submission) {
                    // Check if late (e.g., after 10:00 AM user local time or server time)
                    // For simplicity, using 10 AM server time.
                    // ideally: $submission->created_at->setTimezone($user->timezone)->hour
                    $hour = $submission->created_at->hour;
                    $status = ($hour >= 10) ? 'late' : 'on_time';
                    $meta = $submission->created_at->format('H:i');
                } elseif ($isLeave) {
                    $status = 'leave';
                } elseif ($isWeekend) {
                    $status = 'weekend';
                }

                $userRow['days'][] = [
                    'date' => $dateStr,
                    'status' => $status,
                    'meta' => $meta
                ];
            }
            $matrix[] = $userRow;
        }

        return response()->json([
            'matrix' => $matrix,
            'headers' => $this->getDateHeaders($period)
        ]);
    }

    /**
     * Get the Daily Feed of standups.
     */
    public function getFeed(Request $request)
    {
        [$startDateTime, $endDateTime] = $this->resolveDateRange($request, 7);
        $projectId = $request->input('project_id');
        $userId = $request->input('user_id');

        $query = ProjectNote::with(['creator', 'project', 'noteable']) // noteable for task links
            ->where('type', ProjectNote::STANDUP)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->latest();

        if ($projectId && $projectId !== 'all') {
            $query->where('project_id', $projectId);
        }

        if ($userId && $userId !== 'all') {
            $query->where('creator_id', $userId);
        }

        $notes = $query->paginate(20);

        // Transform data for frontend
        $feed = $notes->getCollection()->map(function ($note) {
            // Content is auto-decrypted by the model accessor
            $content = $note->content;

            // Simple keyword detection for blockers
            $hasBlocker = preg_match('/(block|stuck|issue|problem|fail|error)/i', $content);

            return [
                'id' => $note->id,
                'userId' => $note->creator_id,
                'userName' => $note->creator?->name ?? 'Unknown',
                'userAvatar' => 'https://ui-avatars.com/api/?name=' . urlencode($note->creator?->name ?? 'U') . '&background=random&color=fff',
                'projectId' => $note->project_id,
                'projectName' => $note->project?->name ?? 'General',
                'createdAt' => $note->created_at->toIso8601String(),
                'content' => $content, // Send raw HTML/Text
                'hasBlocker' => (bool) $hasBlocker,
                'taskId' => ($note->noteable_type === 'App\Models\Task') ? $note->noteable_id : null,
                'chatLink' => $note->chat_message_id ? $this->generateChatLink($note->project) : null
            ];
        });

        return response()->json([
            'data' => $feed,
            'next_page_url' => $notes->nextPageUrl(),
            'current_page' => $notes->currentPage(),
        ]);
    }

    /**
     * Get Analytics Stats (Leaderboard, Ghost Projects, Pulse).
     */
    public function getStats()
    {
        // 1. Streak Leaderboard
        // We will calculate streaks based on the last 30 days of activity
        $users = User::whereHas('role', function ($q) {
            $q->whereIn('slug', ['employee', 'manager']);
        })->get();

        $leaderboard = [];
        $today = Carbon::today();

        foreach ($users as $user) {
            // Get dates user submitted standups in last 30 days
            $dates = ProjectNote::where('creator_id', $user->id)
                ->where('type', ProjectNote::STANDUP)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->pluck('created_at')
                ->map(fn($d) => $d->format('Y-m-d'))
                ->unique()
                ->values()
                ->all();

            $streak = 0;
            $checkDate = $today->copy();

            // Simple consecutive day check (ignoring weekends logic for simplicity/speed here)
            // Ideally, we skip weekends in the check loop
            for ($i = 0; $i < 30; $i++) {
                if ($checkDate->isWeekend()) {
                    $checkDate->subDay();
                    continue;
                }

                if (in_array($checkDate->format('Y-m-d'), $dates)) {
                    $streak++;
                    $checkDate->subDay();
                } else {
                    // Allow missing "today" if it's currently morning?
                    // Strict streak: break immediately
                    break;
                }
            }

            if ($streak > 0) {
                $leaderboard[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random&color=fff',
                    'streak' => $streak
                ];
            }
        }

        // Sort by streak desc
        usort($leaderboard, fn($a, $b) => $b['streak'] <=> $a['streak']);
        $leaderboard = array_slice($leaderboard, 0, 5);

        // 2. Ghost Projects
        // Projects with Status Active/In Progress but NO standups in last 48 hours
        $ghostProjects = Project::whereIn('status', ['active', 'in_progress']) // Adjust based on your Enum values
            ->whereHas('milestones', function($q) {
                $q->whereHas('tasks', function($q) {
                    $q->whereIn('status', ['In Progress', 'To Do']); // Projects with actual work remaining
                });
            })
            ->whereDoesntHave('notes', function($q) {
                $q->where('type', ProjectNote::STANDUP)
                    ->where('created_at', '>=', Carbon::now()->subHours(48));
            })
            ->select('id', 'name', 'last_email_received') // select pertinent fields
            ->limit(5)
            ->get()
            ->map(function($p) {
                // Get last known standup date
                $lastNote = $p->notes()
                    ->where('type', ProjectNote::STANDUP)
                    ->latest()
                    ->first();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'lastActivity' => $lastNote ? $lastNote->created_at->diffForHumans() : 'No recent standups'
                ];
            });

        // 3. Engagement Stats
        // Last 7 days stats
        $notesLast7Days = ProjectNote::where('type', ProjectNote::STANDUP)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->get();

        $totalNotes = $notesLast7Days->count();
        $lateNotes = $notesLast7Days->filter(fn($n) => $n->created_at->hour >= 10)->count();
        $blockedNotes = $notesLast7Days->filter(function($n) {
            return preg_match('/(block|stuck|issue|problem)/i', $n->content);
        })->count();

        // Calculate rough completion rate (Standups / (Active Users * 5 Working Days))
        $activeUserCount = $users->count();
        $expected = $activeUserCount * 5;
        $completionRate = $expected > 0 ? round(($totalNotes / $expected) * 100) : 0;
        if($completionRate > 100) $completionRate = 100; // cap at 100

        $stats = [
            'completion' => $completionRate,
            'onTime' => $totalNotes > 0 ? round((($totalNotes - $lateNotes) / $totalNotes) * 100) : 0,
            'blockerRatio' => $totalNotes > 0 ? round(($blockedNotes / $totalNotes) * 100) : 0,
        ];

        return response()->json([
            'leaderboard' => $leaderboard,
            'ghostProjects' => $ghostProjects,
            'stats' => $stats
        ]);
    }

    // --- Helpers ---

    private function getDateHeaders(CarbonPeriod $period)
    {
        $headers = [];
        foreach ($period as $date) {
            $headers[] = [
                'name' => $date->format('D'),
                'date' => $date->format('j M'),
                'full' => $date->format('Y-m-d'),
                'isWeekend' => $date->isWeekend()
            ];
        }
        return $headers;
    }

    private function generateChatLink($project)
    {
        if (!$project || !$project->google_chat_id) return '#';
        $correctedPath = str_replace('spaces/', 'space/', $project->google_chat_id);
        return "https://mail.google.com/chat/u/0/#chat/{$correctedPath}";
    }
}
