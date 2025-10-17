'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchVisitorFollowups } from '@/lib/api/visitors';
import { useTenantId } from '@/lib/tenant';

export function useVisitorFollowups() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['visitor-followups', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return [];
      }
      return fetchVisitorFollowups(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
