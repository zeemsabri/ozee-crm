<?php

namespace Tests\Feature;

use App\Mail\ExternalApiEmail;
use App\Models\Client;
use App\Models\EmailApp;
use App\Models\MagicLink;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExternalEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_email_send_succeeds_for_valid_token_and_linked_active_smtp_app(): void
    {
        Mail::fake();

        $project = $this->createProject();
        $app = $this->createSmtpEmailApp();
        $token = $this->createExternalToken($project, $app->id, 'external-email-valid-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/email/send', [
            'app_id' => $app->id,
            'to' => 'recipient@example.com',
            'subject' => 'Welcome',
            'body_text' => 'Hello from external API',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'sent');

        Mail::assertSent(ExternalApiEmail::class);

        $this->assertDatabaseHas('external_email_logs', [
            'magic_link_id' => $token->id,
            'email_app_id' => $app->id,
            'status' => 'sent',
            'to_email' => 'recipient@example.com',
            'subject' => 'Welcome',
        ]);
    }

    public function test_external_email_send_rejects_when_token_is_not_linked_to_any_email_app(): void
    {
        $project = $this->createProject();
        $app = $this->createSmtpEmailApp();
        $token = $this->createExternalToken($project, null, 'external-email-unlinked-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/email/send', [
            'app_id' => $app->id,
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body_text' => 'Body',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Token is not linked to an email app.');

        $this->assertDatabaseHas('external_email_logs', [
            'magic_link_id' => $token->id,
            'email_app_id' => $app->id,
            'status' => 'rejected',
            'error_message' => 'token_not_linked',
        ]);
    }

    public function test_external_email_send_rejects_when_app_id_does_not_match_token_ownership(): void
    {
        $project = $this->createProject();
        $ownedApp = $this->createSmtpEmailApp('owned-app');
        $otherApp = $this->createSmtpEmailApp('other-app');
        $token = $this->createExternalToken($project, $ownedApp->id, 'external-email-mismatch-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/email/send', [
            'app_id' => $otherApp->id,
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body_text' => 'Body',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Token does not belong to the provided app_id.');

        $this->assertDatabaseHas('external_email_logs', [
            'magic_link_id' => $token->id,
            'email_app_id' => $otherApp->id,
            'status' => 'rejected',
            'error_message' => 'token_app_mismatch',
        ]);
    }

    public function test_external_email_send_rejects_when_linked_email_app_is_inactive(): void
    {
        $project = $this->createProject();
        $app = $this->createSmtpEmailApp('inactive-app', false);
        $token = $this->createExternalToken($project, $app->id, 'external-email-inactive-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/email/send', [
            'app_id' => $app->id,
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body_text' => 'Body',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Email app is inactive.');

        $this->assertDatabaseHas('external_email_logs', [
            'magic_link_id' => $token->id,
            'email_app_id' => $app->id,
            'status' => 'rejected',
            'error_message' => 'email_app_inactive',
        ]);
    }

    public function test_external_email_send_returns_not_supported_for_api_delivery_mode_in_phase_one(): void
    {
        $project = $this->createProject();
        $app = EmailApp::create([
            'name' => 'API Mode App',
            'slug' => 'api-mode-app',
            'is_active' => true,
            'delivery_mode' => 'api',
            'api_provider' => 'sendgrid',
            'api_base_url' => 'https://api.sendgrid.com',
            'api_key' => 'api-key',
        ]);

        $token = $this->createExternalToken($project, $app->id, 'external-email-api-mode-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/email/send', [
            'app_id' => $app->id,
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body_text' => 'Body',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'external_email_api_mode_not_supported');

        $this->assertDatabaseHas('external_email_logs', [
            'magic_link_id' => $token->id,
            'email_app_id' => $app->id,
            'status' => 'not_supported',
            'error_message' => 'api_mode_not_supported_in_phase_1',
        ]);
    }

    public function test_external_payment_routes_still_work_with_unlinked_external_token(): void
    {
        $project = $this->createProject();
        $token = $this->createExternalToken($project, null, 'external-payment-regression-token');

        $response = $this->withHeaders([
            'X-Magic-Token' => $token->token,
        ])->postJson('/api/external/payment/create-session', [
            'app_id' => 'missing-config-app',
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'aud',
                        'product_data' => [
                            'name' => 'Regression test item',
                        ],
                        'unit_amount' => 1000,
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    private function createProject(): Project
    {
        $client = Client::create([
            'name' => 'External Email Client',
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Project::create([
            'name' => 'External Email Project',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
    }

    private function createSmtpEmailApp(string $slug = 'smtp-app', bool $isActive = true): EmailApp
    {
        return EmailApp::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_active' => $isActive,
            'delivery_mode' => 'smtp',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'user@example.com',
            'smtp_password' => 'super-secret',
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'noreply@example.com',
            'smtp_from_name' => 'External Mailer',
        ]);
    }

    private function createExternalToken(Project $project, ?int $emailAppId, string $token): MagicLink
    {
        return MagicLink::create([
            'label' => 'External Email Token',
            'email' => 'external@example.com',
            'token' => $token,
            'type' => 'external',
            'project_id' => $project->id,
            'email_app_id' => $emailAppId,
            'used' => false,
        ]);
    }
}
