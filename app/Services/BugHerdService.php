<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BugHerdService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.bugherd.base_url', env('BUG_HERD_BASE_URL', 'https://www.bugherd.com/api_v2'));
        $this->apiKey = config('services.bugherd.api_key', env('BUG_HERD_API_KEY'));
    }

    /**
     * Get all projects from BugHerd.
     *
     * @return array
     */
    public function getProjects(): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, 'x')
                ->get("{$this->baseUrl}/projects.json");

            if ($response->successful()) {
                return $response->json()['projects'] ?? [];
            }

            Log::error('BugHerd API Category failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('BugHerd API Exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
