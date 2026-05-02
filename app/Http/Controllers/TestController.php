<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Client;
use App\Models\Email;
use App\Models\Project;
use App\Models\User;
use App\Notifications\EmailApproved;
use App\Services\EmailAiAnalysisService;
use App\Services\GmailService;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(protected EmailAiAnalysisService $aiAnalysisService,
        protected GmailService $gmailService,
        protected MagicLinkService $magicLinkService) {}

    /**
     * Test the User model's project role functionality
     */
    public function testUserProjectRole(Request $request)
    {

        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        // Using save() on a new or existing model instance
        $user = User::find(1);
        $user->name = 'Jane';
        $user->save();

        return $user;
        // Get a project ID from the request or use a default
        $projectId = $request->input('project_id');

        if (! $projectId) {
            return response()->json(['error' => 'Please provide a project_id parameter'], 400);
        }

        // Example 1: Using the scope to get users with their roles for a specific project
        $usersWithRoles = User::withProjectRole($projectId)->get();

        // Example 2: Get a specific user's role for a project
        $user = User::find($request->input('user_id', 1)); // Default to user ID 1 if not provided
        $userRole = $user ? $user->getRoleForProject($projectId) : null;

        return response()->json([
            'users_with_roles' => $usersWithRoles,
            'specific_user_role' => [
                'user_id' => $user ? $user->id : null,
                'project_id' => $projectId,
                'role' => $userRole,
            ],
        ]);
    }

    public function playGourd(Request $request)
    {

        //        return Client::availableCategoryOptions();
        //        return Email::availableCategoryOptions('emails');

        $emailDetails = [
            'name' => 'Test Lead',
            'from' => 'test@test1.com',
            'subject' => 'Test Subject',
            'to' => 'info@ozeeweb.com.au',
            'body' => 'Test body',
        ];

        $client = Client::createOrFirst(
            ['email' => $emailDetails['from'] ?? null],
            ['name' => $emailDetails['name'] ?? null]
        );

        $options = Client::availableCategoryOptions('other');
        $client->syncCategories(collect($options)->pluck('value')->toArray());

        $conversation = $client->conversations()->where('subject', $emailDetails['subject'])->first();
        if (! $conversation) {
            $conversation = $client->conversations()->createOrFirst(
                ['subject' => $emailDetails['subject']],
                [
                    'project_id' => $client->projects()->first()?->id,
                    'last_activity_at' => now(),
                ]
            );
        }

        $conversation->emails()->create([
            'sender_type' => get_class($client),
            'sender_id' => $client->id,
            'to' => $emailDetails['to'] ?? null,
            'subject' => $emailDetails['subject'] ?? null,
            'body' => $emailDetails['body'] ?? null,
            'status' => EmailStatus::Unknown,
            'type' => EmailType::Received,
        ]);

        return $conversation;

        return $client;

        $marker = $request->input('marker');
        $source = $request->input('source');

        // --- Step 1: Prepare and validate the marker ---
        $cleanMarker = trim($marker);

        // If the marker is empty after trimming, we can't search for it.
        if ($cleanMarker === '') {
            return $source;
        }

        // --- Step 2: Find the first occurrence of the marker in the text ---
        // We use a case-insensitive search (stripos) for better reliability.
        $markerPosition = stripos($source, $cleanMarker);

        // --- Step 3: Handle the case where the marker is not found ---
        if ($markerPosition === false) {
            // If the marker doesn't exist in the source text, return the original text.
            return $source;
        }

        // --- Step 4: Calculate the length of the text to keep ---
        // This includes all text from the beginning up to the very end of the marker.
        $lengthToKeep = $markerPosition + strlen($cleanMarker);

        // --- Step 5: Extract the desired part of the string and clean it up ---
        $substring = substr($source, 0, $lengthToKeep);

        // Finally, trim the result to remove any unwanted leading/trailing whitespace.
        return trim($substring);
    }

    /**
     * Send a test email using a prefixed env mail configuration.
     *
     * Example prefix: IFAM_QUIZ -> IFAM_QUIZ_MAIL_HOST, IFAM_QUIZ_MAIL_PORT, etc.
     */
    public function testEmailWithConfig(Request $request)
    {
        $validated = $request->validate([
            'config_prefix' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_]+$/'],
            'to' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $prefix = strtoupper((string) $validated['config_prefix']);

        $host = $this->getEnvValue("{$prefix}_MAIL_HOST");
        $port = $this->getEnvValue("{$prefix}_MAIL_PORT");
        $username = $this->getEnvValue("{$prefix}_MAIL_USERNAME");
        $password = $this->getEnvValue("{$prefix}_MAIL_PASSWORD");
        $encryption = $this->getEnvValue("{$prefix}_MAIL_ENCRYPTION") ?: null;
        $fromAddress = $this->getEnvValue("{$prefix}_MAIL_FROM_ADDRESS") ?: config('mail.from.address');
        $fromName = $this->getEnvValue("{$prefix}_MAIL_FROM_NAME") ?: config('mail.from.name');

        $missing = [];
        foreach ([
            "{$prefix}_MAIL_HOST" => $host,
            "{$prefix}_MAIL_PORT" => $port,
            "{$prefix}_MAIL_USERNAME" => $username,
            "{$prefix}_MAIL_PASSWORD" => $password,
            "{$prefix}_MAIL_FROM_ADDRESS" => $fromAddress,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required mail configuration values.',
                'missing' => $missing,
            ], 422);
        }

        $mailerName = 'runtime_test_'.strtolower($prefix);
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'encryption' => $encryption,
                'timeout' => null,
            ],
        ]);

        $subject = $validated['subject'] ?? "Test Email ({$prefix})";
        $body = $validated['body'] ?? "This is a test email using {$prefix} mail configuration.";

        try {
            Mail::mailer($mailerName)->raw($body, function ($message) use ($validated, $subject, $fromAddress, $fromName) {
                $message->to($validated['to'])
                    ->subject($subject)
                    ->from($fromAddress, $fromName);
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully.',
                'mailer' => $mailerName,
                'config_prefix' => $prefix,
                'to' => $validated['to'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email.',
                'error' => $e->getMessage(),
                'config_prefix' => $prefix,
            ], 500);
        }
    }

    public function sendTestNotification(Request $request)
    {
        $message = $request->input('message', 'This is a test notification from Reverb!');

        $userToNotify = User::find(1);
        $email = Email::first();
        $userToNotify->notify(new EmailApproved($email, true));

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent!',
            'data' => [
                'message' => $message,
                'channel' => 'test-channel',
                'event' => 'TestNotification'
            ]
        ]);
    }

    private function getEnvValue(string $key): ?string
    {
        $value = env($key);
        if ($value !== null && $value !== false) {
            return (string) $value;
        }

        $serverValue = $_SERVER[$key] ?? null;
        if ($serverValue !== null && $serverValue !== false) {
            return (string) $serverValue;
        }

        $envValue = $_ENV[$key] ?? null;
        if ($envValue !== null && $envValue !== false) {
            return (string) $envValue;
        }

        $getEnvValue = getenv($key);
        if ($getEnvValue !== false && $getEnvValue !== null) {
            return (string) $getEnvValue;
        }

        return null;
    }
}
