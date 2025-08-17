<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NoticeBoard;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\Project;
use App\Notifications\NoticeCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NoticeBoardController extends Controller
{
    /**
     * Admin: Create a new notice and notify users.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url',
            'type' => 'required|string|in:' . implode(',', NoticeBoard::TYPES),
            'visible_to_client' => 'sometimes|boolean',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:push,email,silent',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'project_id' => 'sometimes|integer|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $data = collect($validated)->only(['title','description','url','type','visible_to_client'])->toArray();
        $data['created_by'] = $request->user()->id;
        // Persist whether push channel was selected
        $data['sent_push'] = in_array('push', $validated['channels'] ?? [], true);

        $notice = NoticeBoard::create($data);

        // Determine recipients
        $recipients = collect();
        if (!empty($validated['user_ids'])) {
            $recipients = User::whereNull('deleted_at')->whereIn('id', $validated['user_ids'])->get();
        } elseif (!empty($validated['project_id'])) {
            $project = Project::with(['users' => function($q){ $q->whereNull('users.deleted_at'); }])->find($validated['project_id']);
            $recipients = $project ? $project->users : collect();
        } else {
            $recipients = User::whereNull('deleted_at')->get();
        }

        // Map requested channels to Laravel channels
        $channelMap = [
            'push' => 'broadcast',
            'email' => 'mail',
            'silent' => 'database',
        ];
        $laravelChannels = collect($validated['channels'] ?? [])
            ->map(fn($c) => $channelMap[$c] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();


        array_push($laravelChannels, 'database');

        foreach ($recipients as $user) {
            $user->notify(new NoticeCreated($notice, $laravelChannels));
        }

        return response()->json(['message' => 'Notice created and notifications sent', 'notice' => $notice, 'recipients' => $recipients->pluck('id')], 201);
    }

    /**
     * List notices (optionally filter by type)
     */
    public function index(Request $request)
    {
        $type = $request->query('type');
        $query = NoticeBoard::orderByDesc('created_at');
        if ($type && in_array($type, NoticeBoard::TYPES, true)) {
            $query->where('type', $type);
        }

        // Paginate notices first
        $notices = $query->paginate(15);

        // Collect notice IDs
        $noticeIds = collect($notices->items())->pluck('id')->all();

        if (!empty($noticeIds)) {
            // Load interactions for these notices with related user
            $interactions = UserInteraction::with('user')
                ->whereIn('interactable_id', $noticeIds)
                ->where('interactable_type', NoticeBoard::class)
                ->orderBy('created_at', 'asc')
                ->get();

            // Group by notice then by user
            $byNotice = $interactions->groupBy('interactable_id');

            // Transform paginator items to include users_with_interactions
            $transformed = collect($notices->items())->map(function ($notice) use ($byNotice) {
                $usersWithInteractions = [];
                $groups = $byNotice->get($notice->id, collect())->groupBy('user_id');
                foreach ($groups as $userId => $rows) {
                    $user = optional($rows->first())->user;
                    if (!$user) { continue; }
                    $usersWithInteractions[] = [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                        'interactions' => $rows->map(function ($r) {
                            return [
                                'type' => $r->interaction_type,
                                'created_at' => $r->created_at,
                            ];
                        })->values()->all(),
                    ];
                }

                // Attach extra field without altering original attributes
                $notice->setAttribute('users_with_interactions', $usersWithInteractions);
                return $notice;
            });

            // Replace paginator collection while keeping metadata
            $notices->setCollection($transformed);
        }

        return response()->json($notices);
    }

    /**
     * Get unread notices for the authenticated user.
     */
    public function unread(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        $unreadNoticeIds = $user->unreadNotifications
                                ->where('type', NoticeCreated::class)
                                ->pluck('data.notice_id');

        $notices = NoticeBoard::orderByDesc('created_at')
            ->whereIn('id', $unreadNoticeIds)
            ->take(10)
            ->get();

        return response()->json(['data' => $notices]);
    }

    /**
     * Acknowledge notices as read.
     */
    /**
     * Mark notifications as read and record user interactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acknowledge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notice_ids' => 'required|array',
            'notice_ids.*' => 'integer|exists:shareable_resources,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $now = now();

        // 1. Mark notifications as read in the 'notifications' table
        $user->notifications()
            ->where('type', NoticeCreated::class)
            ->whereIn('data->notice_id', $request->notice_ids)
            ->update(['read_at' => $now]);

        // 2. Prepare and insert 'read' interactions into the 'user_interactions' table
        $rows = [];
        foreach ($request->notice_ids as $noticeId) {
            $rows[] = [
                'user_id' => $user->id,
                'interactable_id' => $noticeId,
                'interactable_type' => NoticeBoard::class,
                'interaction_type' => 'read',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Using a transaction for atomicity to handle the `firstOrCreate` logic
        // more efficiently for multiple records and prevent race conditions.
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                UserInteraction::firstOrCreate(
                    [
                        'user_id' => $row['user_id'],
                        'interactable_id' => $row['interactable_id'],
                        'interactable_type' => $row['interactable_type'],
                        'interaction_type' => $row['interaction_type']
                    ],
                    $row
                );
            }
        });

        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * Redirect endpoint that logs read + click, then redirects to final URL.
     */
    public function redirect(NoticeBoard $notice, Request $request)
    {
        $user = $request->user();
        if (!$notice->url) {
            return response()->json(['message' => 'No URL for this notice'], 400);
        }

        // Log read and click interactions
        $now = now();
        foreach (['read', 'click'] as $type) {
            try {
                DB::table('user_interactions')->insert([
                    'user_id' => $user->id,
                    'interactable_id' => $notice->id,
                    'interactable_type' => NoticeBoard::class,
                    'interaction_type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // ignore duplicate
            }
        }

        return redirect()->away($notice->url);
    }
}
