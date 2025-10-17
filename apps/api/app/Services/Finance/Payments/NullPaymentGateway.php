<?php

namespace App\Services\Finance\Payments;

class NullPaymentGateway implements PaymentGateway
{
    public function createCustomer(array $payload): array
    {
        return ['status' => 'noop'] + $payload;
    }

    public function charge(array $payload): array
    {
        return ['status' => 'noop'] + $payload;
    }

    public function setupRecurring(array $payload): array
    {
        return ['status' => 'noop'] + $payload;
    }
}
