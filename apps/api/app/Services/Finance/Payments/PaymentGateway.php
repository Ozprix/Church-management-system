<?php

namespace App\Services\Finance\Payments;

interface PaymentGateway
{
    /**
     * Create or update a remote customer profile.
     */
    public function createCustomer(array $payload): array;

    /**
     * Charge a one-time donation.
     */
    public function charge(array $payload): array;

    /**
     * Configure a recurring payment.
     */
    public function setupRecurring(array $payload): array;
}
