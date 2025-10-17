<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        if ($tenant = $this->resolveFromHeaders($request)) {
            return $tenant;
        }

        $host = Str::lower($request->getHost());
        if (!$host) {
            return null;
        }

        if ($tenant = $this->resolveFromCustomDomain($host)) {
            return $tenant;
        }

        if ($tenant = $this->resolveFromSubdomain($host)) {
            return $tenant;
        }

        return null;
    }

    private function resolveFromHeaders(Request $request): ?Tenant
    {
        $headerKeys = config('tenancy.header_keys', []);

        foreach ($headerKeys as $key) {
            $value = $request->headers->get($key);
            if (!$value) {
                continue;
            }

            $tenant = Tenant::query()
                ->where('id', $value)
                ->orWhere('uuid', $value)
                ->orWhere('slug', $value)
                ->first();

            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }

    private function resolveFromCustomDomain(string $host): ?Tenant
    {
        $domain = TenantDomain::query()->where('hostname', $host)->first();

        return $domain?->tenant;
    }

    private function resolveFromSubdomain(string $host): ?Tenant
    {
        $centralDomains = config('tenancy.central_domains', []);
        $reserved = config('tenancy.reserved_subdomains', []);

        foreach ($centralDomains as $central) {
            $central = Str::lower(trim($central));
            if ($central === '') {
                continue;
            }

            if (!Str::endsWith($host, $central)) {
                continue;
            }

            $hostWithoutCentral = Str::beforeLast($host, '.' . $central);
            if ($hostWithoutCentral === $host) {
                // host equals central domain (no subdomain)
                return null;
            }

            $segments = array_values(array_filter(explode('.', $hostWithoutCentral)));
            if (empty($segments)) {
                return null;
            }

            $subdomain = Arr::first($segments);
            if (in_array($subdomain, $reserved, true)) {
                return null;
            }

            return Tenant::query()->where('slug', $subdomain)->first();
        }

        return null;
    }
}
