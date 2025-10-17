'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFinanceDashboard, FinanceDashboardResponse } from '@/lib/api/finance';
import { useTenantId } from '@/lib/tenant';

export function useFinanceDashboard() {
  const tenantId = useTenantId();

  return useQuery<FinanceDashboardResponse>({
    queryKey: ['finance', 'dashboard', tenantId],
    queryFn: () => {
      if (!tenantId) {
        return Promise.reject(new Error('Missing tenant context'));
      }

      return fetchFinanceDashboard(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
