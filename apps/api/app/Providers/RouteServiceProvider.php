<?php

namespace App\Providers;

use App\Events\ThrottleLimitExceeded;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('member-import-upload', function (Request $request) {
            $key = $this->throttleKey($request);

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) use ($key) {
                Log::warning('Member import upload throttle exceeded.', [
                    'key' => $key,
                    'ip' => $request->ip(),
                    'user_id' => optional($request->user())->id,
                    'endpoint' => $request->path(),
                ]);

                Event::dispatch(new ThrottleLimitExceeded(
                    key: $key,
                    route: $request->path(),
                    userId: optional($request->user())->id,
                    ip: $request->ip()
                ));

                return response()->json([
                    'message' => 'Too many member import upload attempts. Please try again shortly.',
                ], 429)->withHeaders($headers);
            });
        });

        RateLimiter::for('member-bulk-operations', function (Request $request) {
            $key = $this->throttleKey($request);

            return Limit::perMinute(10)->by($key)->response(function (Request $request, array $headers) use ($key) {
                Log::warning('Member bulk operation throttle exceeded.', [
                    'key' => $key,
                    'ip' => $request->ip(),
                    'user_id' => optional($request->user())->id,
                    'endpoint' => $request->path(),
                ]);

                Event::dispatch(new ThrottleLimitExceeded(
                    key: $key,
                    route: $request->path(),
                    userId: optional($request->user())->id,
                    ip: $request->ip()
                ));

                return response()->json([
                    'message' => 'Too many member bulk requests. Please slow down.',
                ], 429)->withHeaders($headers);
            });
        });
    }

    protected function throttleKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            return sprintf('user:%d', $user->id);
        }

        return sprintf('ip:%s', $request->ip());
    }
}
