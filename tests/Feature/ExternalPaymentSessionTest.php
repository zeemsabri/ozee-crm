<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MagicLink;
use App\Models\Project;
use App\Models\StripeConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class ExternalPaymentSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{method: string, url: string, params: array}> */
    private array $stripeRequests = [];

    private string $sessionStatus = 'open';

    protected function setUp(): void
    {
        parent::setUp();

        // Fake Stripe HTTP layer: records requests and returns a canned Checkout Session.
        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface
        {
            public function __construct(private ExternalPaymentSessionTest $test) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return [json_encode($this->test->recordStripeRequest($method, $absUrl, $params)), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function recordStripeRequest(string $method, string $url, $params): array
    {
        $this->stripeRequests[] = ['method' => $method, 'url' => $url, 'params' => is_array($params) ? $params : []];
        $embedded = ($params['ui_mode'] ?? null) === 'embedded';

        return [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'status' => str_ends_with($url, '/expire') ? 'expired' : $this->sessionStatus,
            'ui_mode' => $params['ui_mode'] ?? 'hosted',
            'url' => $embedded ? null : 'https://checkout.stripe.com/pay/cs_test_123',
            'client_secret' => $embedded ? 'cs_test_123_secret_abc' : null,
            'expires_at' => time() + 3600,
        ];
    }

    public function test_hosted_session_is_unchanged(): void
    {
        $this->api('/api/external/payment/create-session', $this->sessionPayload([
            'success_url' => 'https://shop.test/success',
            'cancel_url' => 'https://shop.test/cancel',
        ]))
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/pay/cs_test_123')
            ->assertJsonPath('data.client_secret', null);

        $params = $this->stripeRequests[0]['params'];
        $this->assertArrayNotHasKey('ui_mode', $params);
        $this->assertStringStartsWith('https://shop.test/success?activity_id=', $params['success_url']);
    }

    public function test_embedded_session_returns_client_secret_without_redirect_urls(): void
    {
        $this->api('/api/external/payment/create-session', $this->sessionPayload([
            'ui_mode' => 'embedded',
            'return_url' => 'https://shop.test/return',
        ]))
            ->assertOk()
            ->assertJsonPath('data.client_secret', 'cs_test_123_secret_abc')
            ->assertJsonPath('data.ui_mode', 'embedded')
            ->assertJsonPath('data.public_key', 'pk_test_abc');

        $params = $this->stripeRequests[0]['params'];
        $this->assertSame('embedded', $params['ui_mode']);
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $params['return_url']);
        $this->assertArrayNotHasKey('success_url', $params);
        $this->assertArrayNotHasKey('cancel_url', $params);
    }

    public function test_embedded_session_requires_return_url(): void
    {
        $this->api('/api/external/payment/create-session', $this->sessionPayload(['ui_mode' => 'embedded']))
            ->assertStatus(500) // existing create-session behaviour: validation errors are wrapped as 500
            ->assertJsonPath('success', false);

        $this->assertSame([], $this->stripeRequests);
    }

    public function test_cancel_session_expires_stripe_session_and_marks_activity_cancelled(): void
    {
        $activityId = $this->api('/api/external/payment/create-session', $this->sessionPayload([
            'ui_mode' => 'embedded',
            'return_url' => 'https://shop.test/return',
        ]))->json('data.activity_id');

        $this->api('/api/external/payment/cancel-session', ['app_id' => 'app-123', 'activity_id' => $activityId])
            ->assertOk()
            ->assertJsonPath('message', 'Payment session cancelled.');

        $this->assertStringEndsWith('/v1/checkout/sessions/cs_test_123/expire', end($this->stripeRequests)['url']);
        $this->assertSame('cancelled', Activity::find($activityId)->getExtraProperty('status'));

        // A second cancel is rejected rather than silently succeeding.
        $this->api('/api/external/payment/cancel-session', ['app_id' => 'app-123', 'activity_id' => $activityId])
            ->assertStatus(409);
    }

    public function test_cancel_session_rejects_completed_session(): void
    {
        $activityId = $this->api('/api/external/payment/create-session', $this->sessionPayload([
            'ui_mode' => 'embedded',
            'return_url' => 'https://shop.test/return',
        ]))->json('data.activity_id');

        $this->sessionStatus = 'complete';

        $this->api('/api/external/payment/cancel-session', ['app_id' => 'app-123', 'activity_id' => $activityId])
            ->assertStatus(409);

        $this->assertSame('pending', Activity::find($activityId)->getExtraProperty('status'));
    }

    public function test_cancel_session_rejects_activity_from_another_app(): void
    {
        $activityId = $this->api('/api/external/payment/create-session', $this->sessionPayload([
            'ui_mode' => 'embedded',
            'return_url' => 'https://shop.test/return',
        ]))->json('data.activity_id');

        StripeConfiguration::create(['app_name' => 'Other', 'app_id' => 'app-other', 'stripe_secret_key' => 'sk_test_other', 'stripe_public_key' => 'pk_test_other']);

        $this->api('/api/external/payment/cancel-session', ['app_id' => 'app-other', 'activity_id' => $activityId])
            ->assertNotFound();
    }

    private function api(string $uri, array $data)
    {
        return $this->withHeaders(['X-Magic-Token' => 'external-payment-token'])->postJson($uri, $data);
    }

    private function sessionPayload(array $overrides): array
    {
        if (! StripeConfiguration::where('app_id', 'app-123')->exists()) {
            StripeConfiguration::create(['app_name' => 'Shop', 'app_id' => 'app-123', 'stripe_secret_key' => 'sk_test_abc', 'stripe_public_key' => 'pk_test_abc']);

            $client = Client::create(['name' => 'Payment Client', 'email' => fake()->unique()->safeEmail()]);
            $project = Project::create(['name' => 'Payment Project', 'client_id' => $client->id, 'status' => 'active']);
            MagicLink::create([
                'label' => 'External Payment Token',
                'email' => 'external@example.com',
                'token' => 'external-payment-token',
                'type' => 'external',
                'project_id' => $project->id,
                'used' => false,
            ]);
        }

        return array_merge([
            'app_id' => 'app-123',
            'line_items' => [['price_data' => ['currency' => 'aud', 'product_data' => ['name' => 'Item'], 'unit_amount' => 2500], 'quantity' => 1]],
        ], $overrides);
    }
}
