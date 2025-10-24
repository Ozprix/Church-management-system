'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFinanceAnalytics, FinanceAnalyticsFilters, FinanceAnalyticsResponse } from '@/lib/api/finance';
import { useTenantId } from '@/lib/tenant';

export function useFinanceAnalytics(filters: FinanceAnalyticsFilters = {}) {
  const tenantId = useTenantId();

  return useQuery<FinanceAnalyticsResponse | null>({
    queryKey: ['finance-analytics', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }

      return fetchFinanceAnalytics(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
