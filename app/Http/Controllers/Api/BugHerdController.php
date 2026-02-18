<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BugHerdService;
use Illuminate\Http\JsonResponse;

class BugHerdController extends Controller
{
    protected BugHerdService $bugHerdService;

    public function __construct(BugHerdService $bugHerdService)
    {
        $this->bugHerdService = $bugHerdService;
    }

    /**
     * Get all BugHerd projects.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $projects = $this->bugHerdService->getProjects();
        return response()->json($projects);
    }
}
