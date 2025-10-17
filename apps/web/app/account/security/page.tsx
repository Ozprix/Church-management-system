'use client';

import { FormEvent, useEffect, useMemo, useState, useTransition } from 'react';
import {
  adminResetTwoFactor,
  confirmTwoFactor,
  disableTwoFactor,
  fetchCurrentUser,
  regenerateRecoveryCodes,
  startTwoFactor,
  type CurrentUser,
  type TwoFactorSetupResponse,
} from '@/lib/api/auth';
import { ApiError } from '@/lib/api/http';
import { useTenantId } from '@/lib/tenant';

export default function AccountSecurityPage() {
  const tenantId = useTenantId();
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [abilities, setAbilities] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [setupData, setSetupData] = useState<TwoFactorSetupResponse | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [confirmCode, setConfirmCode] = useState('');
  const [regenerateCode, setRegenerateCode] = useState('');
  const [disableCode, setDisableCode] = useState('');
  const [disableRecoveryCode, setDisableRecoveryCode] = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    if (!tenantId) {
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchCurrentUser(tenantId)
      .then((response) => {
        if (cancelled) {
          return;
        }
        setUser(response.user);
        setAbilities(response.abilities ?? []);
      })
      .catch((err: unknown) => {
        if (cancelled) {
          return;
        }
        const message = err instanceof ApiError ? err.message : 'Unable to load account details.';
        setError(message);
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [tenantId]);

  const canManageOthers = useMemo(() => abilities.includes('*') || abilities.includes('users.manage_security'), [abilities]);

  const handleStartSetup = () => {
    if (!tenantId) return;
    setError(null);
    setStatus(null);
    startTransition(async () => {
      try {
        const data = await startTwoFactor(tenantId);
        setSetupData(data);
        setRecoveryCodes(data.recovery_codes);
        setStatus('Two-factor setup initialized. Scan the QR code or manually enter the secret, then confirm below.');
      } catch (err) {
        const message = err instanceof ApiError ? err.message : 'Failed to generate setup information.';
        setError(message);
      }
    });
  };

  const handleConfirmTwoFactor = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!tenantId || !confirmCode) return;
    setError(null);
    setStatus(null);
    startTransition(async () => {
      try {
        await confirmTwoFactor(confirmCode, tenantId);
        setStatus('Two-factor authentication enabled. Store your recovery codes in a secure location.');
        setSetupData(null);
        setConfirmCode('');
        setUser((current) => (current ? { ...current, two_factor_enabled: true } : current));
      } catch (err) {
        const message = err instanceof ApiError ? err.message : 'Failed to confirm the authentication code.';
        setError(message);
      }
    });
  };

  const handleRegenerateCodes = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!tenantId || !regenerateCode) return;
    setError(null);
    setStatus(null);
    startTransition(async () => {
      try {
        const response = await regenerateRecoveryCodes(regenerateCode, tenantId);
        setRecoveryCodes(response.recovery_codes);
        setStatus('Recovery codes refreshed. Save the new codes immediately.');
        setRegenerateCode('');
      } catch (err) {
        const message = err instanceof ApiError ? err.message : 'Could not regenerate recovery codes.';
        setError(message);
      }
    });
  };

  const handleDisableTwoFactor = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!tenantId) return;
    setError(null);
    setStatus(null);
    startTransition(async () => {
      try {
        await disableTwoFactor({ code: disableCode || undefined, recovery_code: disableRecoveryCode || undefined }, tenantId);
        setStatus('Two-factor authentication disabled. Tokens have been revoked for this user.');
        setDisableCode('');
        setDisableRecoveryCode('');
        setUser((current) => (current ? { ...current, two_factor_enabled: false } : current));
        setRecoveryCodes(null);
      } catch (err) {
        const message = err instanceof ApiError ? err.message : 'Failed to disable two-factor authentication.';
        setError(message);
      }
    });
  };

  const handleAdminReset = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!tenantId || !adminEmail) return;
    setError(null);
    setStatus(null);
    startTransition(async () => {
      try {
        const response = await adminResetTwoFactor(adminEmail, tenantId);
        setStatus(response.message);
        setAdminEmail('');
      } catch (err) {
        const message = err instanceof ApiError ? err.message : 'Failed to reset two-factor authentication for the user.';
        setError(message);
      }
    });
  };

  if (loading) {
    return <p className="text-sm text-slate-600">Loading account security information…</p>;
  }

  if (error && !user) {
    return <p className="text-sm text-red-600">{error}</p>;
  }

  if (!user) {
    return <p className="text-sm text-red-600">Unable to determine the current user.</p>;
  }

  return (
    <div className="space-y-10">
      <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Two-factor authentication</h2>
        <p className="mt-1 text-sm text-slate-600">
          Protect your account by requiring a time-based one-time password (TOTP) in addition to your
          email and password. Use an authenticator app such as Google Authenticator, 1Password, or Authy.
        </p>

        <div className="mt-4 flex flex-col gap-2 text-sm">
          <div>
            <span className="font-medium text-slate-700">Status:</span>{' '}
            <span className={user.two_factor_enabled ? 'text-emerald-600' : 'text-orange-600'}>
              {user.two_factor_enabled ? 'Enabled' : 'Not enabled'}
            </span>
          </div>
        </div>

        {status ? <p className="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">{status}</p> : null}
        {error ? <p className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p> : null}

        <div className="mt-6 space-y-6">
          {!user.two_factor_enabled && (
            <div className="space-y-4">
              <div>
                <button
                  type="button"
                  onClick={handleStartSetup}
                  disabled={isPending}
                  className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-400"
                >
                  Start setup
                </button>
              </div>

              {setupData && (
                <div className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                  <p className="font-semibold">Setup details</p>
                  <p className="mt-2">
                    Scan the provisioning URI below with your authenticator application or manually enter the
                    secret. After scanning, enter a verification code to complete setup.
                  </p>
                  <div className="mt-4 space-y-2">
                    <div>
                      <span className="font-medium text-slate-800">Secret:</span>
                      <code className="ml-2 rounded bg-white px-2 py-1 text-sm text-slate-900 shadow-inner">
                        {setupData.secret}
                      </code>
                    </div>
                    <div className="overflow-hidden rounded border border-dashed border-slate-300 bg-white p-3 text-xs text-slate-600">
                      <p className="font-semibold text-slate-700">Provisioning URI</p>
                      <p className="break-all">{setupData.qr_code_uri}</p>
                    </div>
                    <div>
                      <p className="font-medium text-slate-800">Recovery codes</p>
                      <p className="text-xs text-slate-500">
                        Store these codes in a secure password manager. Each code can be used once if you lose
                        access to your authenticator.
                      </p>
                      <ul className="mt-2 grid grid-cols-2 gap-2 text-xs font-mono text-slate-700 md:grid-cols-4">
                        {setupData.recovery_codes.map((code) => (
                          <li key={code} className="rounded bg-white px-2 py-1 shadow-sm">
                            {code}
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>

                  <form onSubmit={handleConfirmTwoFactor} className="mt-4 flex flex-col gap-2 md:flex-row md:items-center">
                    <label className="text-sm font-medium text-slate-700 md:w-48" htmlFor="confirm-code">
                      Verification code
                    </label>
                    <input
                      id="confirm-code"
                      type="text"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      maxLength={6}
                      value={confirmCode}
                      onChange={(event) => setConfirmCode(event.target.value)}
                      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 md:max-w-xs"
                      placeholder="123456"
                    />
                    <button
                      type="submit"
                      disabled={isPending || confirmCode.length === 0}
                      className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-400"
                    >
                      Confirm
                    </button>
                  </form>
                </div>
              )}
            </div>
          )}

          {user.two_factor_enabled && (
            <div className="space-y-6">
              <div>
                <h3 className="text-sm font-semibold text-slate-800">Recovery codes</h3>
                <p className="text-xs text-slate-500">
                  Generate a new set of recovery codes by supplying a current authenticator code.
                </p>
                <form onSubmit={handleRegenerateCodes} className="mt-3 flex flex-col gap-2 md:flex-row md:items-center">
                  <label className="text-sm font-medium text-slate-700 md:w-48" htmlFor="regen-code">
                    Authenticator code
                  </label>
                  <input
                    id="regen-code"
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={6}
                    value={regenerateCode}
                    onChange={(event) => setRegenerateCode(event.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 md:max-w-xs"
                    placeholder="123456"
                  />
                  <button
                    type="submit"
                    disabled={isPending || regenerateCode.length === 0}
                    className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-400"
                  >
                    Regenerate codes
                  </button>
                </form>
              </div>

              {recoveryCodes && recoveryCodes.length > 0 && (
                <div className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                  <p className="font-medium text-slate-800">New recovery codes</p>
                  <p className="text-xs text-slate-500">Copy these codes to a secure location. Older codes are no longer valid.</p>
                  <ul className="mt-2 grid grid-cols-2 gap-2 text-xs font-mono md:grid-cols-4">
                    {recoveryCodes.map((code) => (
                      <li key={code} className="rounded bg-white px-2 py-1 shadow-sm">
                        {code}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              <div>
                <h3 className="text-sm font-semibold text-slate-800">Disable two-factor authentication</h3>
                <p className="text-xs text-slate-500">
                  You will be asked to reconfigure two-factor authentication the next time you log in.
                </p>
                <form onSubmit={handleDisableTwoFactor} className="mt-3 space-y-3">
                  <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <label className="text-sm font-medium text-slate-700 md:w-48" htmlFor="disable-code">
                      Authenticator code
                    </label>
                    <input
                      id="disable-code"
                      type="text"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      maxLength={6}
                      value={disableCode}
                      onChange={(event) => setDisableCode(event.target.value)}
                      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 md:max-w-xs"
                      placeholder="123456"
                    />
                  </div>
                  <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <label className="text-sm font-medium text-slate-700 md:w-48" htmlFor="disable-recovery">
                      Recovery code (optional)
                    </label>
                    <input
                      id="disable-recovery"
                      type="text"
                      value={disableRecoveryCode}
                      onChange={(event) => setDisableRecoveryCode(event.target.value)}
                      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 md:max-w-xs"
                      placeholder="ABCDEF1234"
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={isPending || (!disableCode && !disableRecoveryCode)}
                    className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
                  >
                    Disable two-factor authentication
                  </button>
                </form>
              </div>
            </div>
          )}
        </div>
      </section>

      {canManageOthers && (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Operator reset</h2>
          <p className="mt-1 text-sm text-slate-600">
            Reset another team member&apos;s two-factor authentication when they lose access to their authenticator. A
            notification email will be sent automatically and any existing tokens will be revoked.
          </p>

          <form onSubmit={handleAdminReset} className="mt-4 flex flex-col gap-3 md:flex-row md:items-end">
            <div className="flex-1">
              <label className="text-sm font-medium text-slate-700" htmlFor="admin-reset-email">
                User email
              </label>
              <input
                id="admin-reset-email"
                type="email"
                required
                value={adminEmail}
                onChange={(event) => setAdminEmail(event.target.value)}
                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500"
                placeholder="user@example.com"
              />
            </div>
            <button
              type="submit"
              disabled={isPending || adminEmail.length === 0}
              className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-400"
            >
              Reset two-factor
            </button>
          </form>
        </section>
      )}
    </div>
  );
}
