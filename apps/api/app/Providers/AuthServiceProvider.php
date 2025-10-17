<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $modules = config('permissions.modules', []);

        $superPermission = config('permissions.super_permission', null);

        Gate::before(function (?User $user) use ($superPermission) {
            if (! $user) {
                return null;
            }

            if ($superPermission && $user->hasPermission($superPermission)) {
                return true;
            }

            if ($user->hasPermission('*')) {
                return true;
            }

            return null;
        });

        foreach ($modules as $module) {
            $permissions = Arr::get($module, 'permissions', []);

            foreach ($permissions as $slug => $meta) {
                Gate::define($slug, static function (?User $user) use ($slug): bool {
                    return $user?->hasPermission($slug) ?? false;
                });
            }
        }

        if ($superPermission) {
            Gate::define($superPermission, static function (?User $user) use ($superPermission): bool {
                return $user?->hasPermission($superPermission) ?? false;
            });
        }
    }
}
