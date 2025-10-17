'use client';

import { useEffect, useState } from 'react';
import { resolveTenantFromHostname } from '@church/utils';

export function useTenantId() {
  const [tenantId, setTenantId] = useState<string | undefined>(undefined);

  useEffect(() => {
    const hostname = window.location.hostname;
    const { tenantSlug } = resolveTenantFromHostname(hostname);
    if (tenantSlug) {
      setTenantId(tenantSlug);
      return;
    }

    const isLocalhost =
      hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    const fallback =
      process.env.NEXT_PUBLIC_TENANT_ID || (isLocalhost ? 'example' : undefined);
    if (fallback) {
      setTenantId(fallback);
    }
  }, []);

  return tenantId;
}
