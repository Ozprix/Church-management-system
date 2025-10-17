import { apiFetch, ApiError } from '@/lib/api/http';

export type CurrentUser = {
  id: number;
  tenant_id: number;
  name: string | null;
  email: string;
  two_factor_enabled: boolean;
  two_factor_confirmed_at?: string | null;
  roles?: Array<{ id: number; name: string; slug: string }>;
  permissions: string[];
};

export type CurrentUserResponse = {
  user: CurrentUser;
  abilities: string[];
};

export type TwoFactorSetupResponse = {
  secret: string;
  qr_code_uri: string;
  recovery_codes: string[];
};

export type RecoveryCodesResponse = {
  recovery_codes: string[];
};

export async function fetchCurrentUser(tenantId?: string): Promise<CurrentUserResponse> {
  return apiFetch<CurrentUserResponse>('/api/v1/auth/me', {}, tenantId);
}

export async function startTwoFactor(tenantId?: string): Promise<TwoFactorSetupResponse> {
  return apiFetch<TwoFactorSetupResponse>(
    '/api/v1/auth/two-factor/setup',
    {
      method: 'POST',
    },
    tenantId
  );
}

export async function confirmTwoFactor(code: string, tenantId?: string): Promise<{ message: string }> {
  return apiFetch<{ message: string }>(
    '/api/v1/auth/two-factor/confirm',
    {
      method: 'POST',
      body: JSON.stringify({ code }),
    },
    tenantId
  );
}

export async function regenerateRecoveryCodes(code: string, tenantId?: string): Promise<RecoveryCodesResponse> {
  return apiFetch<RecoveryCodesResponse>(
    '/api/v1/auth/two-factor/recovery-codes',
    {
      method: 'POST',
      body: JSON.stringify({ code }),
    },
    tenantId
  );
}

export async function disableTwoFactor(
  options: { code?: string; recovery_code?: string },
  tenantId?: string
): Promise<void> {
  await apiFetch<unknown>(
    '/api/v1/auth/two-factor',
    {
      method: 'DELETE',
      body: JSON.stringify(options),
    },
    tenantId
  );
}

export async function adminResetTwoFactor(email: string, tenantId?: string): Promise<{ message: string }> {
  return apiFetch<{ message: string }>(
    '/api/v1/auth/two-factor/admin-reset',
    {
      method: 'POST',
      body: JSON.stringify({ email }),
    },
    tenantId
  );
}
