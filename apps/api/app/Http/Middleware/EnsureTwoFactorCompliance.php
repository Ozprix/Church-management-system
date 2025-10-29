<?php

namespace App\Http\Middleware;

use App\Services\Security\TenantSecurityPolicyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureTwoFactorCompliance
{
    public function __construct(
        private readonly TenantSecurityPolicyService $policies
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if ($this->policies->requiresTwoFactor($user) && ! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is required for your assigned roles before accessing this resource.'),
                'reason' => 'two_factor_required',
            ], JsonResponse::HTTP_LOCKED);
        }

        return $next($request);
    }

    private function shouldBypass(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        $exemptNames = [
            'auth.me',
            'auth.logout',
            'auth.2fa.setup',
            'auth.2fa.confirm',
            'auth.2fa.recovery',
            'auth.2fa.disable',
        ];

        if ($routeName && in_array($routeName, $exemptNames, true)) {
            return true;
        }

        $path = $request->path();

        if (str_starts_with($path, 'api/v1/auth/two-factor')) {
            return true;
        }

        return false;
    }
}
