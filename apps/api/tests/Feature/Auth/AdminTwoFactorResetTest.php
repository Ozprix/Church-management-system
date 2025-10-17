<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTwoFactorResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_two_factor_for_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'resetme@example.com',
        ]);

        $secret = $twoFactor->generateSecret($user);
        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $user->createToken('legacy');

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/two-factor/admin-reset', [
                'email' => 'resetme@example.com',
            ]);

        $response->assertOk();

        $user->refresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'user.two_factor_reset',
            'auditable_id' => $user->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient' => 'resetme@example.com',
            'channel' => 'email',
        ]);
    }

    public function test_user_without_permission_cannot_reset(): void
    {
        $tenant = Tenant::factory()->create();
        $actingUser = $this->actingAsTenantUser(tenant: $tenant, roles: ['member_manager'], grantSuperPermission: false);

        $this->assertFalse($actingUser->hasPermission('users.manage_security'));

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'target@example.com',
        ]);

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/two-factor/admin-reset', [
                'email' => 'target@example.com',
            ])
            ->assertForbidden();
    }
}
