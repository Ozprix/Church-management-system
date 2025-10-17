export interface TenantResolution {
  tenantSlug: string | null;
  isCustomDomain: boolean;
}

/**
 * Derives the active tenant slug based on hostname and optional mapping of custom domains.
 */
const RESERVED_SUBDOMAINS = new Set(['app', 'www']);

export function resolveTenantFromHostname(
  hostname: string,
  customDomains: Record<string, string> = {}
): TenantResolution {
  const normalizedHost = hostname.trim().toLowerCase();
  if (!normalizedHost) {
    return { tenantSlug: null, isCustomDomain: false };
  }

  if (customDomains[normalizedHost]) {
    return { tenantSlug: customDomains[normalizedHost], isCustomDomain: true };
  }

  if (normalizedHost.endsWith('.localhost')) {
    const tenantCandidate = normalizedHost.slice(0, -'.localhost'.length);
    if (tenantCandidate) {
      return { tenantSlug: tenantCandidate, isCustomDomain: false };
    }
  }

  const parts = normalizedHost.split('.');
  if (parts.length <= 2) {
    return { tenantSlug: null, isCustomDomain: false };
  }

  const subdomain = parts[0];
  if (RESERVED_SUBDOMAINS.has(subdomain)) {
    return { tenantSlug: null, isCustomDomain: false };
  }

  return { tenantSlug: subdomain, isCustomDomain: false };
}
