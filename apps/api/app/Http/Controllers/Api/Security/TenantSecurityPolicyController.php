<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UpdateTenantSecurityPolicyRequest;
use App\Models\Tenant;
use App\Models\TenantSecurityPolicy;
use App\Services\Security\TenantSecurityPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSecurityPolicyController extends Controller
{
    public function __construct(
        private readonly TenantSecurityPolicyService $policies
    ) {
        $this->middleware('can:users.manage_security');
    }

    public function show(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $policy = $this->policies->getPolicyForTenant($tenant);

        return response()->json([
            'policy' => $this->formatPolicy($policy),
        ]);
    }

    public function update(UpdateTenantSecurityPolicyRequest $request): JsonResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $policy = $this->policies->updatePolicy($tenant, $request->validated());

        return response()->json([
            'policy' => $this->formatPolicy($policy),
        ]);
    }

    public function compliance(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);

        return response()->json(
            $this->policies->buildComplianceReport($tenant)
        );
    }

    private function formatPolicy(TenantSecurityPolicy $policy): array
    {
        return [
            'enforce_two_factor' => (bool) $policy->enforce_two_factor,
            'enforced_role_slugs' => $policy->enforced_role_slugs ?? [],
            'enforced_permission_slugs' => $policy->enforced_permission_slugs ?? [],
        ];
    }

    private function resolveTenantFromRequest(Request $request): Tenant
    {
        $user = $request->user();
        $user?->loadMissing('tenant');

        $tenant = $user?->tenant;

        if (! $tenant) {
            abort(400, __('Unable to determine tenant for the current request.'));
        }

        return $tenant;
    }
}
