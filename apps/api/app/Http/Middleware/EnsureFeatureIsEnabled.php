<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Rbac\RbacManager;
use App\Services\TenantOnboardingService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureIsEnabled
{
    public function __construct(
        private readonly RbacManager $rbacManager,
        private readonly TenantOnboardingService $tenantOnboardingService
    )
    {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if (! $tenant) {
            return new JsonResponse([
                'message' => 'Tenant context is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (! $this->rbacManager->featureEnabledForTenant($tenant->id, $feature)) {
            return new JsonResponse([
                'message' => 'Feature unavailable for this tenant.',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = $this->tenantOnboardingService->featureLimit($tenant, $feature);
        if ($limit === 0) {
            return new JsonResponse([
                'message' => 'Plan limits prevent access to this feature.',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
