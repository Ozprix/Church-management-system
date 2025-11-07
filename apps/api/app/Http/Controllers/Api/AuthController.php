<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use App\Services\Security\TenantSecurityPolicyService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly TenantSecurityPolicyService $securityPolicies
    ) {
    }

    public function login(Request $request, TenantManager $tenantManager): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $tenant = $tenantManager->getTenant();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'email' => __('Unable to resolve tenant for this request.'),
            ]);
        }

        /** @var User|null $user */
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials provided.'),
            ]);
        }

        $user->setRelation('tenant', $tenant);

        $policy = $this->securityPolicies->getPolicyForTenant($tenant);
        $requiresTwoFactor = $this->securityPolicies->requiresTwoFactor($user, $policy);

        if ($user->hasTwoFactorEnabled()) {
            $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);

            if (empty($credentials['code']) && empty($credentials['recovery_code'])) {
                return response()->json([
                    'message' => __('Two-factor authentication code required.'),
                    'two_factor_required' => true,
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated = false;

            if (! empty($credentials['code']) && $secret) {
                $validated = $this->twoFactor->verifyCode($secret, $credentials['code']);
            }

            if (! $validated && ! empty($credentials['recovery_code'])) {
                $validated = $this->twoFactor->verifyRecoveryCode($user, $credentials['recovery_code']);
            }

            if (! $validated) {
                throw ValidationException::withMessages([
                    'code' => __('Invalid authentication code or recovery code provided.'),
                ]);
            }
        }

        $user->loadMissing(['roles.permissions', 'permissions', 'tenant']);

        $abilities = $user->allPermissionSlugs()
            ->merge(['*'])
            ->unique()
            ->values()
            ->all();

        // session hardening: revoke prior tokens
        $user->tokens()->delete();

        $accessToken = $user->createToken('api', $abilities);
        $tokenModel = $accessToken->accessToken;
        $tokenModel->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ])->save();

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'user' => UserResource::make($user),
            'two_factor' => [
                'enforced' => (bool) $policy->enforce_two_factor,
                'required' => $requiresTwoFactor,
                'compliant' => $user->hasTwoFactorEnabled(),
                'enforced_role_slugs' => $policy->enforced_role_slugs ?? [],
                'enforced_permission_slugs' => $policy->enforced_permission_slugs ?? [],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([], 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles.permissions', 'permissions', 'tenant']);
        $tenant = $user->tenant;
        $policy = $tenant ? $this->securityPolicies->getPolicyForTenant($tenant) : null;
        $requiresTwoFactor = $policy ? $this->securityPolicies->requiresTwoFactor($user, $policy) : false;

        return response()->json([
            'user' => UserResource::make($user),
            'abilities' => $user->allPermissionSlugs()->values(),
            'two_factor' => [
                'enforced' => (bool) ($policy?->enforce_two_factor ?? false),
                'required' => $requiresTwoFactor,
                'compliant' => $user->hasTwoFactorEnabled(),
                'enforced_role_slugs' => $policy?->enforced_role_slugs ?? [],
                'enforced_permission_slugs' => $policy?->enforced_permission_slugs ?? [],
            ],
        ]);
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $this->twoFactor->generateSecret($user);
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr_code_uri' => $this->twoFactor->makeQrUri($user, $secret),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);

        if (! $secret || ! $this->twoFactor->verifyCode($secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('Invalid authentication code provided.'),
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => __('Two-factor authentication has been enabled.'),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user = $user->fresh() ?? $user; // ensure we operate on the latest persisted attributes
        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);

        if (! $user->hasTwoFactorEnabled() || ! $secret || ! $this->twoFactor->verifyCode($secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('Invalid authentication code provided.'),
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
        ])->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user = $user->fresh() ?? $user; // pull a fresh copy in case two-factor fields changed outside this request
        $user->loadMissing('tenant');

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([], 204);
        }

        $policy = $user->tenant ? $this->securityPolicies->getPolicyForTenant($user->tenant) : null;

        if ($policy
            && config('security.two_factor.lock_disable_for_enforced_users', true)
            && $this->securityPolicies->requiresTwoFactor($user, $policy)
        ) {
            return response()->json([
                'message' => __('Two-factor authentication is required for your current role and cannot be disabled.'),
                'reason' => 'two_factor_enforced',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);
        $validatedCode = false;

        if (! empty($validated['code']) && $secret) {
            $validatedCode = $this->twoFactor->verifyCode($secret, $validated['code']);
        }

        if (! $validatedCode && ! empty($validated['recovery_code'])) {
            $validatedCode = $this->twoFactor->verifyRecoveryCode($user, $validated['recovery_code']);
        }

        if (! $validatedCode) {
            throw ValidationException::withMessages([
                'code' => __('A valid authentication code or recovery code is required to disable two-factor authentication.'),
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->tokens()->delete();

        return response()->json([], 204);
    }
}
