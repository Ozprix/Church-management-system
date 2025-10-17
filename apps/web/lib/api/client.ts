import { createApiClient } from '@church/contracts';
import { getApiBaseUrl } from '@/lib/api/env';

type AuthOptions = {
  tenantId?: string;
};

export function createTenantApiClient(options: AuthOptions = {}) {
  const baseUrl = getApiBaseUrl();
  const headers: Record<string, string> = {};
  if (options.tenantId) {
    headers['X-Tenant-ID'] = options.tenantId;
  }

  return createApiClient({
    baseUrl,
    defaultHeaders: headers,
  });
}
