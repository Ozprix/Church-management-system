<?php

namespace App\Services\Finance\Payments;

use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function createCustomer(array $payload): array
    {
        return [
            'id' => $payload['id'] ?? 'cus_' . Str::random(14),
            'email' => $payload['email'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'provider' => 'stripe',
        ];
    }

    public function charge(array $payload): array
    {
        return [
            'id' => 'pi_' . Str::random(14),
            'status' => 'succeeded',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'usd',
            'metadata' => $payload['metadata'] ?? [],
            'provider' => 'stripe',
            'mock' => $this->config['mock'] ?? true,
        ];
    }

    public function setupRecurring(array $payload): array
    {
        return [
            'id' => 'sub_' . Str::random(14),
            'status' => 'active',
            'interval' => $payload['interval'] ?? 'month',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'usd',
            'provider' => 'stripe',
            'mock' => $this->config['mock'] ?? true,
        ];
    }
}
