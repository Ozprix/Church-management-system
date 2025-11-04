<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Donation;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\RecurringDonationAttempt;
use App\Models\RecurringDonationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';
    private const WEBHOOK_URI = '/api/webhooks/stripe';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
            'services.stripe.mock' => true,
        ]);
    }

    public function test_payment_intent_succeeded_updates_donation_and_logs(): void
    {
        Storage::fake('reports');
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'pending',
            'receipt_number' => null,
            'provider_reference' => 'pi_success_123',
        ]);

        $payload = [
            'id' => 'evt_success',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_success_123',
                    'metadata' => [
                        'donation_id' => $donation->id,
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => 'succeeded',
            'provider_reference' => 'pi_success_123',
        ]);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'status' => 'processed',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_payment_intent_succeeded_attaches_existing_attempt(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $schedule = RecurringDonationSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'pending',
            'provider_reference' => 'pi_attempt_001',
        ]);

        $attempt = RecurringDonationAttempt::factory()->create([
            'schedule_id' => $schedule->id,
            'donation_id' => null,
            'status' => 'pending',
            'amount' => $donation->amount,
            'currency' => $donation->currency,
            'provider_reference' => 'pi_attempt_001',
        ]);

        $payload = [
            'id' => 'evt_attempt_success',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_attempt_001',
                    'metadata' => [
                        'donation_id' => $donation->id,
                        'schedule_id' => $schedule->id,
                        'attempt_id' => $attempt->id,
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $attempt->refresh();
        $this->assertSame('succeeded', $attempt->status);
        $this->assertNotNull($attempt->processed_at);
        $this->assertSame($donation->id, $attempt->donation_id);
        $this->assertNull($attempt->failure_reason);
    }

    public function test_payment_intent_failed_creates_attempt_for_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $schedule = RecurringDonationSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'pending',
            'provider_reference' => 'pi_attempt_fail',
        ]);

        $payload = [
            'id' => 'evt_attempt_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_attempt_fail',
                    'metadata' => [
                        'donation_id' => $donation->id,
                        'schedule_id' => $schedule->id,
                    ],
                    'last_payment_error' => [
                        'message' => 'Insufficient funds',
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $attempt = RecurringDonationAttempt::query()
            ->where('schedule_id', $schedule->id)
            ->where('donation_id', $donation->id)
            ->latest()
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('Insufficient funds', $attempt->failure_reason);
        $this->assertSame('stripe', $attempt->provider);
        $this->assertSame('pi_attempt_fail', $attempt->provider_reference);
    }

    public function test_payment_intent_failed_records_failure_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'pending',
            'provider_reference' => 'pi_fail_456',
        ]);

        $payload = [
            'id' => 'evt_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_fail_456',
                    'metadata' => [
                        'donation_id' => $donation->id,
                    ],
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $donation->refresh();
        $this->assertSame('failed', $donation->status);
        $this->assertSame('Your card was declined.', $donation->metadata['failure_reason'] ?? null);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'stripe',
            'event_type' => 'payment_intent.payment_failed',
            'status' => 'processed',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_charge_refunded_marks_donation_refunded(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'succeeded',
            'provider_reference' => 'pi_refund_789',
        ]);

        $payload = [
            'id' => 'evt_refund',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_789',
                    'payment_intent' => 'pi_refund_789',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => 'refunded',
        ]);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'stripe',
            'event_type' => 'charge.refunded',
            'status' => 'processed',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_invalid_signature_is_rejected_without_logging(): void
    {
        $tenant = Tenant::factory()->create();

        $payload = [
            'id' => 'evt_invalid',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_invalid',
                ],
            ],
        ];

        $timestamp = (string) now()->timestamp;
        $headers = [
            'Stripe-Signature' => "t={$timestamp},v1=invalid_signature",
            'X-Tenant-ID' => $tenant->uuid,
        ];

        $response = $this
            ->withHeaders($headers)
            ->postJson(self::WEBHOOK_URI, $payload);

        $response->assertStatus(400);

        $this->assertDatabaseCount('payment_webhook_logs', 0);
    }

    public function test_succeeded_event_without_matching_donation_marks_log_processed(): void
    {
        $tenant = Tenant::factory()->create();

        $payload = [
            'id' => 'evt_missing_donation',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_not_found',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload, $tenant);

        $response->assertOk();

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'status' => 'processed',
            'tenant_id' => null,
        ]);
    }

    private function postSignedWebhook(array $payload, ?Tenant $tenant = null)
    {
        $secret = config('services.stripe.webhook_secret');
        $encoded = $this->encodePayload($payload);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $encoded, $secret);

        $headers = [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ];

        if ($tenant) {
            $headers['X-Tenant-ID'] = $tenant->uuid;
        }

        return $this
            ->withHeaders($headers)
            ->postJson(self::WEBHOOK_URI, $payload);
    }

    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
