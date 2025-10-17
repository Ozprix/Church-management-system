<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookService $webhookService)
    {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret');

        if ($secret && ! $this->isValidSignature($payload, $signature, $secret)) {
            Log::warning('Stripe webhook signature verification failed.');

            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response('Invalid payload', 400);
        }

        $log = $this->webhookService->recordLog($event, 'stripe', $event['type'] ?? null);
        $this->webhookService->handleStripeEvent($event, $log);

        return response('OK', 200);
    }

    protected function isValidSignature(string $payload, string $header, string $secret): bool
    {
        if (! $header) {
            return false;
        }

        $parts = collect(explode(',', $header))
            ->mapWithKeys(function ($part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $computedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($computedSignature, $signature);
    }
}
