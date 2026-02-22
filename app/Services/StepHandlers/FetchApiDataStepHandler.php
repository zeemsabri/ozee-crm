<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchApiDataStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];

        $url = $this->engine->getTemplatedValue($cfg['api_url'] ?? '', $context);
        if (empty($url)) {
            throw new \InvalidArgumentException('API Endpoint URL is required for FETCH_API_DATA action.');
        }

        $method = strtoupper($cfg['api_method'] ?? 'GET');
        $authType = strtoupper($cfg['api_auth_type'] ?? 'NONE');
        $payloadStr = $cfg['api_payload'] ?? '';
        $responseKey = $cfg['api_response_key'] ?? '';

        // Parse and template payload
        $payload = [];
        if (!empty(trim($payloadStr))) {
            $templatedPayloadStr = $this->engine->getTemplatedValue($payloadStr, $context);
            $parsedPayload = json_decode((string) $templatedPayloadStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsedPayload)) {
                $payload = $parsedPayload;
            } else {
                Log::warning('FetchApiDataStepHandler: Invalid JSON payload provided.', [
                    'step_id' => $step->id,
                    'json_error' => json_last_error_msg()
                ]);
            }
        }

        $req = Http::asJson()->acceptJson();

        // Apply Authentication
        if ($authType === 'BEARER') {
            $token = $this->engine->getTemplatedValue($cfg['api_auth_token'] ?? '', $context);
            if (!empty($token)) {
                $req->withToken($token);
            }
        } elseif ($authType === 'BASIC') {
            $username = $this->engine->getTemplatedValue($cfg['api_auth_username'] ?? '', $context);
            $password = $this->engine->getTemplatedValue($cfg['api_auth_password'] ?? '', $context);
            if (!empty($username)) {
                $req->withBasicAuth($username, $password);
            }
        } elseif ($authType === 'CUSTOM_HEADER') {
            $headerName = $this->engine->getTemplatedValue($cfg['api_auth_header_name'] ?? '', $context);
            $headerValue = $this->engine->getTemplatedValue($cfg['api_auth_header_value'] ?? '', $context);
            if (!empty($headerName) && !empty($headerValue)) {
                $req->withHeaders([$headerName => $headerValue]);
            }
        }

        // Execute Request
        $start = microtime(true);
        try {
            if ($method === 'GET') {
                $response = $req->get($url, $payload);
            } elseif ($method === 'POST') {
                $response = $req->post($url, $payload);
            } elseif ($method === 'PUT') {
                $response = $req->put($url, $payload);
            } elseif ($method === 'PATCH') {
                $response = $req->patch($url, $payload);
            } elseif ($method === 'DELETE') {
                $response = $req->delete($url, $payload);
            } else {
                throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("API Request failed: " . $e->getMessage());
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        if (!$response->successful()) {
            Log::error('FetchApiDataStepHandler: API execution returned error status.', [
                'step_id' => $step->id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException("API Request returned status {$response->status()}: " . $response->body());
        }

        $responseData = $response->json();

        // Filter by response key if provided
        $finalData = $responseData;
        if (!empty($responseKey) && is_array($responseData)) {
            $finalData = \Illuminate\Support\Arr::get($responseData, $responseKey, null);
        }

        $parsed = [
            'status' => $response->status(),
            'parsed' => $finalData,
            'duration_ms' => $durationMs
        ];

        return [
            'parsed' => $parsed,
            'context' => [],
            'logs' => [
                'api_url' => $url,
                'api_method' => $method,
                'response_status' => $response->status()
            ]
        ];
    }
}
