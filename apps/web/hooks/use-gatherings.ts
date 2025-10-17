'use client';

import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchGatherings, GatheringFilters } from '@/lib/api/gatherings';
import { useTenantId } from '@/lib/tenant';

export function useGatherings(filters: GatheringFilters = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['gatherings', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchGatherings(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}
