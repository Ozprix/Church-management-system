<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AssignPlanRequest;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\VerifyDomainRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantDomainResource;
use App\Http\Resources\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantOnboardingService $tenantOnboardingService)
    {
        $this->middleware('feature:tenancy')->except(['store', 'verifyDomain', 'plans']);
        $this->middleware('can:tenancy.manage_onboarding')->only(['store']);
        $this->middleware('can:tenancy.manage_plans')->only(['assignPlan']);
        $this->middleware('can:tenancy.view_plans')->only(['profile']);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::query()->where('is_active', true)->with('features')->orderBy('monthly_price')->get();

        return PlanResource::collection($plans)->response();
    }

    public function profile(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $profile = $this->tenantOnboardingService->profile($tenant);

        return response()->json(['data' => $profile]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantOnboardingService->createTenant($request->validated());

        return TenantResource::make($tenant->load('domains'))->response()->setStatusCode(201);
    }

    public function assignPlan(AssignPlanRequest $request, Tenant $tenant): JsonResponse
    {
        $plan = Plan::query()->where('code', $request->string('plan_code'))->firstOrFail();

        $subscription = $this->tenantOnboardingService->assignPlan($tenant, $plan, $request->validated());

        return response()->json([
            'data' => [
                'tenant_plan' => $subscription,
            ],
        ]);
    }

    public function verifyDomain(VerifyDomainRequest $request, Tenant $tenant, int $tenantDomain): JsonResponse
    {
        $domain = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($tenantDomain);

        $verified = $this->tenantOnboardingService->verifyDomain($domain, $request->string('token'));

        if (! $verified) {
            return response()->json(['message' => 'Invalid verification token.'], 422);
        }

        return response()->json([
            'data' => [
                'domain' => TenantDomainResource::make($domain->fresh()),
            ],
        ]);
    }
}
