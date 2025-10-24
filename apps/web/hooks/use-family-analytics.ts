'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFamilyAnalytics, FamilyAnalyticsFilters, FamilyAnalyticsResponse } from '@/lib/api/families';
import { useTenantId } from '@/lib/tenant';

export function useFamilyAnalytics(filters: FamilyAnalyticsFilters = {}) {
  const tenantId = useTenantId();

  return useQuery<FamilyAnalyticsResponse | null>({
    queryKey: ['family-analytics', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }

      return fetchFamilyAnalytics(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
