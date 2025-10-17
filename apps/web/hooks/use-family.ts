'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchFamily } from '@/lib/api/families';
import { useTenantId } from '@/lib/tenant';

export function useFamily(id: number | undefined) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['family', tenantId, id],
    queryFn: async () => {
      if (!tenantId || !id) {
        return null;
      }
      return fetchFamily(tenantId, id);
    },
    enabled: Boolean(tenantId && id),
  });
}
