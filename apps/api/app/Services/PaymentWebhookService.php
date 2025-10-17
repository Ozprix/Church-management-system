<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\PaymentWebhookLog;
use App\Models\RecurringDonationAttempt;
use App\Models\RecurringDonationSchedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(private readonly FinanceService $financeService, private readonly RecurringDonationService $recurringDonationService)
    {
    }

    public function recordLog(array $payload, string $provider, ?string $eventType = null): PaymentWebhookLog
    {
        return PaymentWebhookLog::create([
            'provider' => $provider,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    public function markProcessed(PaymentWebhookLog $log, ?int $tenantId = null, ?string $message = null): void
    {
        $log->tenant_id = $tenantId;
        $log->status = 'processed';
        $log->processed_at = Carbon::now();
        $log->message = $message;
        $log->save();
    }

    public function markFailed(PaymentWebhookLog $log, ?string $message = null): void
    {
        $log->status = 'failed';
        $log->processed_at = Carbon::now();
        $log->message = $message;
        $log->save();
    }

    public function handleStripeEvent(array $event, PaymentWebhookLog $log): void
    {
        $type = $event['type'] ?? null;
        $dataObject = Arr::get($event, 'data.object', []);
        $tenantId = null;

        try {
            switch ($type) {
                case 'payment_intent.succeeded':
                    $tenantId = $this->handleStripePaymentSucceeded($dataObject);
                    $this->markProcessed($log, $tenantId, 'Payment intent succeeded.');
                    break;
                case 'payment_intent.payment_failed':
                    $tenantId = $this->handleStripePaymentFailed($dataObject);
                    $this->markProcessed($log, $tenantId, 'Payment intent failed.');
                    break;
                case 'charge.refunded':
                    $tenantId = $this->handleStripeChargeRefunded($dataObject);
                    $this->markProcessed($log, $tenantId, 'Charge refunded.');
                    break;
                default:
                    $this->markProcessed($log, null, 'Event ignored.');
                    break;
            }
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            $this->markFailed($log, $exception->getMessage());
        }
    }

    protected function handleStripePaymentSucceeded(array $payload): ?int
    {
        $donation = $this->resolveDonationFromStripePayload($payload);
        if (! $donation) {
            return null;
        }

        $this->financeService->updateDonation($donation, [
            'status' => 'succeeded',
            'provider' => 'stripe',
            'provider_reference' => $payload['id'] ?? $donation->provider_reference,
        ]);

        $this->attachRecurringAttempt($donation, $payload, 'succeeded');

        return $donation->tenant_id;
    }

    protected function handleStripePaymentFailed(array $payload): ?int
    {
        $donation = $this->resolveDonationFromStripePayload($payload);
        if (! $donation) {
            return null;
        }

        $this->financeService->updateDonation($donation, [
            'status' => 'failed',
            'metadata' => array_merge($donation->metadata ?? [], [
                'failure_reason' => Arr::get($payload, 'last_payment_error.message'),
            ]),
        ]);

        $this->attachRecurringAttempt($donation, $payload, 'failed', Arr::get($payload, 'last_payment_error.message'));

        return $donation->tenant_id;
    }

    protected function handleStripeChargeRefunded(array $payload): ?int
    {
        $paymentIntentId = Arr::get($payload, 'payment_intent');
        $donation = $this->findDonationByReference($paymentIntentId);
        if (! $donation) {
            return null;
        }

        $this->financeService->updateDonation($donation, [
            'status' => 'refunded',
            'notes' => trim($donation->notes . '\nRefunded via Stripe.'),
        ]);

        $this->attachRecurringAttempt($donation, $payload, 'failed', 'Refunded');

        return $donation->tenant_id;
    }

    protected function resolveDonationFromStripePayload(array $payload): ?Donation
    {
        $donationId = Arr::get($payload, 'metadata.donation_id');
        if ($donationId) {
            return Donation::query()->find($donationId);
        }

        $paymentIntentId = Arr::get($payload, 'id');
        if (! $paymentIntentId) {
            $paymentIntentId = Arr::get($payload, 'payment_intent');
        }

        return $this->findDonationByReference($paymentIntentId);
    }

    protected function findDonationByReference(?string $reference): ?Donation
    {
        if (! $reference) {
            return null;
        }

        return Donation::query()->where('provider_reference', $reference)->first();
    }

    protected function attachRecurringAttempt(
        Donation $donation,
        array $payload,
        string $status,
        ?string $message = null
    ): void {
        $scheduleId = Arr::get($payload, 'metadata.schedule_id');
        $attemptId = Arr::get($payload, 'metadata.attempt_id');

        if ($attemptId) {
            $attempt = RecurringDonationAttempt::query()->find($attemptId);
            if ($attempt) {
                $attempt->donation_id = $donation->id;
                $attempt->status = $status === 'succeeded' ? 'succeeded' : 'failed';
                $attempt->processed_at = Carbon::now();
                $attempt->failure_reason = $message;
                $attempt->save();

                return;
            }
        }

        if ($scheduleId) {
            $schedule = RecurringDonationSchedule::query()->find($scheduleId);
            if ($schedule) {
                RecurringDonationAttempt::create([
                    'schedule_id' => $schedule->id,
                    'donation_id' => $donation->id,
                    'status' => $status === 'succeeded' ? 'succeeded' : 'failed',
                    'amount' => $donation->amount,
                    'currency' => $donation->currency,
                    'provider' => 'stripe',
                    'provider_reference' => Arr::get($payload, 'id'),
                    'processed_at' => Carbon::now(),
                    'failure_reason' => $message,
                ]);
            }
        }
    }
}
