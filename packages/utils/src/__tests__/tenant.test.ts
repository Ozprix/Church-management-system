import { describe, expect, it } from 'vitest';
import { resolveTenantFromHostname } from '../tenant/slug';

describe('resolveTenantFromHostname', () => {
  it('extracts subdomain as tenant slug', () => {
    expect(resolveTenantFromHostname('grace.churchly.app')).toEqual({ tenantSlug: 'grace', isCustomDomain: false });
  });

  it('detects custom domain mapping', () => {
    const custom = { 'gracechapel.org': 'grace' };
    expect(resolveTenantFromHostname('gracechapel.org', custom)).toEqual({ tenantSlug: 'grace', isCustomDomain: true });
  });

  it('returns null when hostname has no tenant segment', () => {
    expect(resolveTenantFromHostname('app.churchly.app')).toEqual({ tenantSlug: null, isCustomDomain: false });
  });
});
