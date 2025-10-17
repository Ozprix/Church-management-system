<?php

namespace App\Services\Finance\Payments;

use Illuminate\Support\Str;

class MobileMoneyGateway implements PaymentGateway
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function createCustomer(array $payload): array
    {
        return [
            'id' => $payload['id'] ?? 'mm_' . Str::random(10),
            'phone' => $payload['phone'] ?? null,
            'provider' => $this->config['provider'] ?? 'mobile_money',
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function charge(array $payload): array
    {
        return [
            'id' => 'mm_txn_' . Str::random(10),
            'status' => 'pending',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'GHS',
            'provider' => $this->config['provider'] ?? 'mobile_money',
            'mock' => $this->config['mock'] ?? true,
        ];
    }

    public function setupRecurring(array $payload): array
    {
        return [
            'id' => 'mm_sub_' . Str::random(10),
            'status' => 'scheduled',
            'interval' => $payload['interval'] ?? 'month',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'GHS',
            'provider' => $this->config['provider'] ?? 'mobile_money',
            'mock' => $this->config['mock'] ?? true,
        ];
    }
}
