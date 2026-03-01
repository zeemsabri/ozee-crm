<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProductivity;
use App\Services\ProductivityReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserProductivityController extends Controller
{
    protected $reportService;

    public function __construct(ProductivityReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * List user productivity snapshots.
     */
    public function index(Request $request)
    {
        $query = UserProductivity::with('user');

        if ($request->has('user_ids')) {
            $userIds = is_array($request->user_ids) ? $request->user_ids : explode(',', $request->user_ids);
            $query->whereIn('user_id', $userIds);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }

        if ($request->boolean('all')) {
            $snapshots = $query->orderBy('date', 'desc')->get();
        } else {
            $snapshots = $query->orderBy('date', 'desc')->paginate(50);
        }

        return response()->json($snapshots);
    }

    /**
     * Create a new productivity snapshot for a user and date.
     */
    public function store(Request $request)
    {
        $v = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'recreate' => 'boolean'
        ]);

        $user = User::findOrFail($v['user_id']);
        $date = Carbon::parse($v['date'])->toDateString();

        $existing = UserProductivity::where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        if ($existing && !$request->boolean('recreate')) {
            return response()->json([
                'message' => 'A productivity report already exists for this date. Please delete it first or use the recreate flag.',
                'report' => $existing
            ], 422);
        }

        $report = $this->reportService->generateDailySnapshot($user, $date);

        return response()->json([
            'message' => 'Report generated successfully.',
            'report' => $report
        ]);
    }

    /**
     * Show a specific productivity snapshot.
     */
    public function show(UserProductivity $userProductivity)
    {
        return response()->json($userProductivity->load('user'));
    }

    /**
     * Delete a productivity snapshot.
     */
    public function destroy(UserProductivity $userProductivity)
    {
        $userProductivity->delete();

        return response()->json([
            'message' => 'Report deleted successfully.'
        ]);
    }
}
