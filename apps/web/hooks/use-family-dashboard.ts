'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFamilyDashboard } from '@/lib/api/families';
import { useTenantId } from '@/lib/tenant';

export function useFamilyDashboard() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['family-dashboard', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }
      return fetchFamilyDashboard(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
