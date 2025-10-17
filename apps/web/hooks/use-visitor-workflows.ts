'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchVisitorWorkflows } from '@/lib/api/visitors';
import { useTenantId } from '@/lib/tenant';

export function useVisitorWorkflows() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['visitor-workflows', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return [];
      }
      return fetchVisitorWorkflows(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
