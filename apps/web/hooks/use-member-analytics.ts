'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchMemberAnalytics, MemberAnalyticsFilters } from '@/lib/api/members';
import { useTenantId } from '@/lib/tenant';

export function useMemberAnalytics(filters: MemberAnalyticsFilters = {}) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['member-analytics', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }
      return fetchMemberAnalytics(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
