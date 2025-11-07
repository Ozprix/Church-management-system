<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return response()->json(['data' => []]);
        }

        $days = (int) $request->integer('days', 7);
        $days = max(1, min($days, 30));
        $since = Carbon::now()->subDays($days);

        $stats = Notification::query()
            ->selectRaw("
                channel,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('queued', 'sending') THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        $latestFailures = Notification::query()
            ->select(['channel', 'error_message', 'updated_at'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'failed')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('channel')
            ->keyBy('channel');

        $channels = collect(['sms', 'email']);

        $data = $channels
            ->map(fn (string $channel): array => $this->formatChannelHealth(
                $channel,
                $stats,
                $latestFailures,
                $days
            ))
            ->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * @param  Collection<string, mixed>  $stats
     * @param  Collection<string, mixed>  $latestFailures
     */
    private function formatChannelHealth(
        string $channel,
        Collection $stats,
        Collection $latestFailures,
        int $days
    ): array {
        $row = $stats->get($channel);
        $queued = (int) ($row->queued ?? 0);
        $sent = (int) ($row->sent ?? 0);
        $failed = (int) ($row->failed ?? 0);
        $total = (int) ($row->total ?? 0);

        $deliveryRate = $total > 0
            ? round(($sent / max($total, 1)) * 100, 1)
            : null;

        $health = 'idle';
        if ($total === 0) {
            $health = 'idle';
        } elseif ($failed > 0 && ($failed / max($total, 1)) >= 0.15) {
            $health = 'degraded';
        } else {
            $health = 'healthy';
        }

        return [
            'channel' => $channel,
            'window_days' => $days,
            'totals' => [
                'queued' => $queued,
                'sent' => $sent,
                'failed' => $failed,
            ],
            'delivery_rate' => $deliveryRate,
            'health' => $health,
            'last_error' => optional($latestFailures->get($channel))->error_message,
            'provider' => $this->providerMeta($channel),
        ];
    }

    private function providerMeta(string $channel): array
    {
        if ($channel === 'sms') {
            $config = config('services.twilio');
            $configured = filled($config['sid'] ?? null)
                && filled($config['token'] ?? null)
                && filled($config['from'] ?? null)
                && ! ($config['mock'] ?? false);

            return [
                'name' => 'Twilio',
                'configured' => $configured,
                'details' => [
                    'from' => $config['from'] ?? null,
                ],
            ];
        }

        $config = config('services.mailgun');
        $configured = filled($config['domain'] ?? null)
            && filled($config['secret'] ?? null)
            && filled($config['from'] ?? null)
            && ! ($config['mock'] ?? false);

        return [
            'name' => 'Mailgun',
            'configured' => $configured,
            'details' => [
                'domain' => $config['domain'] ?? null,
                'from' => $config['from'] ?? null,
            ],
        ];
    }
}
