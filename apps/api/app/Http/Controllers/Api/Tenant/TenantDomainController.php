<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantDomainRequest;
use App\Http\Resources\TenantDomainResource;
use App\Models\TenantDomain;
use App\Services\PlanEnforcementService;
use App\Services\TenantOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDomainController extends Controller
{
    public function __construct(
        private readonly TenantOnboardingService $tenantOnboardingService,
        private readonly PlanEnforcementService $planEnforcementService
    )
    {
        $this->middleware('feature:tenancy');
        $this->middleware('can:tenancy.manage_onboarding');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return TenantDomainResource::collection($tenant->domains()->get())->response();
    }

    public function store(StoreTenantDomainRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $this->planEnforcementService->ensureCanUse($tenant, 'domains');

        $domain = $this->tenantOnboardingService->addDomain($tenant, $request->validated());
        $this->planEnforcementService->recordUsage($tenant, 'domains');

        return TenantDomainResource::make($domain)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, TenantDomain $tenantDomain): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        abort_unless($tenantDomain->tenant_id === $tenant->id, 404);

        $this->tenantOnboardingService->deleteDomain($tenantDomain);
        $this->planEnforcementService->releaseUsage($tenant, 'domains');

        return response()->json([], 204);
    }

    public function regenerate(Request $request, TenantDomain $tenantDomain): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        abort_unless($tenantDomain->tenant_id === $tenant->id, 404);

        $domain = $this->tenantOnboardingService->regenerateDomainToken($tenantDomain);

        return TenantDomainResource::make($domain)->response();
    }
}
