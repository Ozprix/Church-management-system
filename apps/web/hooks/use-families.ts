'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFamilies, type FamilyFilters } from '@/lib/api/families';
import { useTenantId } from '@/lib/tenant';

export function useFamilies(filters: FamilyFilters = {}) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['families', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }
      return fetchFamilies(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
