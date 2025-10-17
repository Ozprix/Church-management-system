<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Models\Donation;
use App\Models\PaymentWebhookLog;
use App\Models\RecurringDonationSchedule;
use App\Models\Tenant;
use App\Services\PaymentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class PaymentWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_succeeded_updates_donation(): void
    {
        $tenant = Tenant::factory()->create();
        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'stripe',
            'provider_reference' => 'pi_123',
            'status' => 'pending',
        ]);

        $event = [
            'id' => 'evt_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123',
                    'metadata' => [
                        'donation_id' => $donation->id,
                    ],
                ],
            ],
        ];

        $service = App::make(PaymentWebhookService::class);
        $log = PaymentWebhookLog::create([
            'provider' => 'stripe',
            'payload' => $event,
        ]);
        $service->handleStripeEvent($event, $log);

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('payment_webhook_logs', [
            'id' => $log->id,
            'status' => 'processed',
        ]);
    }

    public function test_charge_refunded_marks_donation_refunded(): void
    {
        $tenant = Tenant::factory()->create();
        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'stripe',
            'provider_reference' => 'pi_456',
            'status' => 'succeeded',
        ]);

        $event = [
            'id' => 'evt_2',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_456',
                    'payment_intent' => 'pi_456',
                ],
            ],
        ];

        $service = App::make(PaymentWebhookService::class);
        $log = PaymentWebhookLog::create([
            'provider' => 'stripe',
            'payload' => $event,
        ]);
        $service->handleStripeEvent($event, $log);

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('payment_webhook_logs', [
            'id' => $log->id,
            'status' => 'processed',
        ]);
    }
}
