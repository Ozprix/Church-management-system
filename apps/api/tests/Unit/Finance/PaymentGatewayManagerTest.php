<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Services\Finance\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    public function test_it_returns_stripe_gateway_payloads(): void
    {
        Config::set('services.stripe.mock', true);

        $manager = new PaymentGatewayManager();
        $charge = $manager->charge('stripe', ['amount' => 1000, 'currency' => 'usd']);

        $this->assertSame('stripe', $charge['provider']);
        $this->assertSame('succeeded', $charge['status']);
        $this->assertSame(1000, $charge['amount']);
    }

    public function test_it_returns_mobile_money_gateway_payloads(): void
    {
        Config::set('services.mobile_money', ['provider' => 'hubtel', 'mock' => true]);

        $manager = new PaymentGatewayManager();
        $setup = $manager->setupRecurring('mobile_money', ['amount' => 200, 'currency' => 'GHS']);

        $this->assertSame('hubtel', $setup['provider']);
        $this->assertSame('scheduled', $setup['status']);
        $this->assertSame(200, $setup['amount']);
    }

    public function test_it_defaults_to_null_gateway(): void
    {
        $manager = new PaymentGatewayManager();
        $result = $manager->charge('unknown', ['foo' => 'bar']);

        $this->assertSame('noop', $result['status']);
        $this->assertSame('bar', $result['foo']);
    }
}
