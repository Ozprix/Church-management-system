'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchGathering } from '@/lib/api/gatherings';
import { useTenantId } from '@/lib/tenant';

export function useGathering(uuid: string | undefined) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['gathering', tenantId, uuid],
    queryFn: async () => {
      if (!tenantId || !uuid) return null;
      return fetchGathering(tenantId, uuid);
    },
    enabled: Boolean(tenantId && uuid),
  });
}
