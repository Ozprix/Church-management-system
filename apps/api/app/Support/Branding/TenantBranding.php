<?php

namespace App\Support\Branding;

use App\Models\Tenant;
use Illuminate\Support\Arr;

class TenantBranding
{
    /**
     * @return array<string, mixed>
     */
    public static function resolve(Tenant $tenant): array
    {
        $branding = Arr::get($tenant->meta ?? [], 'branding', []);

        return [
            'organization' => $tenant->name,
            'logo_url' => Arr::get($branding, 'logo_url'),
            'primary_color' => Arr::get($branding, 'primary_color', config('reports.branding.primary_color')),
            'accent_color' => Arr::get($branding, 'accent_color', config('reports.branding.accent_color')),
            'address' => Arr::get($branding, 'address', Arr::get($tenant->meta ?? [], 'address')),
            'phone' => Arr::get($branding, 'phone'),
            'email' => Arr::get($branding, 'email'),
            'website' => Arr::get($branding, 'website'),
        ];
    }
}
