<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'login@example.com',
            'password' => bcrypt('secret-pass'),
        ]);

        $rbac->assignRole($user, 'admin');

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/login', [
                'email' => 'login@example.com',
                'password' => 'secret-pass',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'abilities',
                'user' => ['id', 'name', 'email', 'permissions'],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'wrong@example.com',
            'password' => bcrypt('correct-pass'),
        ]);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/login', [
                'email' => 'wrong@example.com',
                'password' => 'incorrect',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $rbac->assignRole($user, 'admin');

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_me_returns_authenticated_user_details(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_protected_route_requires_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/members');

        $response->assertStatus(401);
    }

    public function test_login_requires_two_factor_code_when_enabled(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => '2fa@example.com',
            'password' => bcrypt('password123'),
        ]);
        $rbac->assignRole($user, 'admin');

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($user);

        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/login', [
                'email' => '2fa@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('two_factor_required', true);
    }

    public function test_login_with_two_factor_code_succeeds(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'totp@example.com',
            'password' => bcrypt('password123'),
        ]);
        $rbac->assignRole($user, 'admin');

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($user);
        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $code = $twoFactor->currentCode($secret);

        $user->createToken('stale-token');

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/login', [
                'email' => 'totp@example.com',
                'password' => 'password123',
                'code' => $code,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_setup_and_confirm_two_factor_flow(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        Carbon::setTestNow(now());

        $setupResponse = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/two-factor/setup');

        $setupResponse->assertOk()
            ->assertJsonStructure(['secret', 'qr_code_uri', 'recovery_codes']);

        $payload = $setupResponse->json();

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);

        $confirmResponse = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/auth/two-factor/confirm', [
                'code' => $twoFactor->currentCode($payload['secret']),
            ]);

        $confirmResponse->assertOk();

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());

        Carbon::setTestNow();
    }

    public function test_regenerate_recovery_codes_requires_valid_code(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($user);
        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $code = $twoFactor->currentCode($secret);

        app()->instance(TwoFactorAuthenticationService::class, new class($secret, $code) extends TwoFactorAuthenticationService {
            public function __construct(private string $expectedSecret, private string $expectedCode)
            {
            }

            public function verifyCode(string $secret, string $code): bool
            {
                if ($secret === $this->expectedSecret && $code === $this->expectedCode) {
                    return true;
                }

                return parent::verifyCode($secret, $code);
            }
        });

        try {
            $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
                ->postJson('/api/v1/auth/two-factor/recovery-codes', [
                    'code' => $code,
                ]);
        } finally {
            app()->forgetInstance(TwoFactorAuthenticationService::class);
        }

        $response->assertOk()->assertJsonStructure(['recovery_codes']);

        $user->refresh();
        $this->assertNotEmpty($user->two_factor_recovery_codes);

    }

    public function test_disable_two_factor_with_recovery_code(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($user);
        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->deleteJson('/api/v1/auth/two-factor', [
                'recovery_code' => Arr::first($recoveryCodes),
            ]);

        $response->assertNoContent();
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }
}
