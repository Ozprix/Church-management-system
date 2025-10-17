<?php

namespace App\Services\Finance\Payments;

class PaymentGatewayManager
{
    public function gateway(string $provider): PaymentGateway
    {
        return match ($provider) {
            'stripe' => new StripePaymentGateway(config('services.stripe', [])),
            'mobile_money', 'momo' => new MobileMoneyGateway(config('services.mobile_money', [])),
            default => new NullPaymentGateway(),
        };
    }

    public function charge(string $provider, array $payload): array
    {
        return $this->gateway($provider)->charge($payload);
    }

    public function setupRecurring(string $provider, array $payload): array
    {
        return $this->gateway($provider)->setupRecurring($payload);
    }

    public function createCustomer(string $provider, array $payload): array
    {
        return $this->gateway($provider)->createCustomer($payload);
    }
}
