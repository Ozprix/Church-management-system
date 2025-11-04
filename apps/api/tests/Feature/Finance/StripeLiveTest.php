<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeLiveTest extends TestCase
{
    public function test_it_confirms_payment_intent_against_live_stripe(): void
    {
        $secret = env('STRIPE_SECRET');
        $runLive = env('RUN_STRIPE_LIVE_TESTS', false);

        if (! $secret || ! $runLive) {
            $this->markTestSkipped('Stripe live credentials not configured.');
        }

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => 123,
                'currency' => 'usd',
                'payment_method' => 'pm_card_visa',
                'confirm' => 'true',
                'description' => 'Live verification payment',
            ]);

        $this->assertTrue($response->successful(), $response->body());

        $payload = $response->json();

        $this->assertSame('succeeded', $payload['status'] ?? null);
        $this->assertSame(123, $payload['amount'] ?? null);
        $this->assertSame('usd', $payload['currency'] ?? null);
    }
}
