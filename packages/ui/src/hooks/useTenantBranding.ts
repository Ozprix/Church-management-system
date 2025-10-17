import { useMemo } from 'react';

export interface TenantBranding {
  primaryColor: string;
  secondaryColor: string;
  accentColor: string;
}

const DEFAULT_BRANDING: TenantBranding = {
  primaryColor: '#047857',
  secondaryColor: '#0f172a',
  accentColor: '#ec4899'
};

/**
 * Returns a color palette that can be used to theme UI components based on the tenant slug.
 * In the full application this would pull from the tenant configuration coming from the API.
 */
export function useTenantBranding(tenantSlug?: string | null): TenantBranding {
  return useMemo(() => {
    if (!tenantSlug) {
      return DEFAULT_BRANDING;
    }

    const hash = Array.from(tenantSlug).reduce((acc, char) => acc + char.charCodeAt(0), 0);
    const offset = hash % TENANT_COLOR_PALETTES.length;
    return TENANT_COLOR_PALETTES[offset];
  }, [tenantSlug]);
}

const TENANT_COLOR_PALETTES: TenantBranding[] = [
  { primaryColor: '#047857', secondaryColor: '#0f172a', accentColor: '#ec4899' },
  { primaryColor: '#1d4ed8', secondaryColor: '#111827', accentColor: '#f59e0b' },
  { primaryColor: '#7c3aed', secondaryColor: '#1f2937', accentColor: '#f97316' }
];
