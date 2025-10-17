'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchServices, ServiceFilters } from '@/lib/api/services';
import { useTenantId } from '@/lib/tenant';

export function useServices(filters: ServiceFilters = {}) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['services', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchServices(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
