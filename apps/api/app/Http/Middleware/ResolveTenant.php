<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantManager;
use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantManager $manager
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (!$tenant) {
            $route = $request->route();
            $allowWithoutTenant = $route?->defaults['allow_without_tenant'] ?? false;

            if (! $allowWithoutTenant) {
                return new JsonResponse([
                    'message' => 'Tenant not found for request.',
                ], Response::HTTP_NOT_FOUND);
            }

            return $next($request);
        }

        $this->manager->setTenant($tenant);
        $request->attributes->set('tenant', $tenant);

        /** @var Response $response */
        $response = $next($request);

        $this->manager->forgetTenant();

        return $response;
    }
}
