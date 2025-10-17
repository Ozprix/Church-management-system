'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchMember } from '@/lib/api/members';
import { useTenantId } from '@/lib/tenant';

export function useMember(uuid: string | undefined) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['member', tenantId, uuid],
    queryFn: async () => {
      if (!tenantId || !uuid) {
        return null;
      }

      return fetchMember(tenantId, uuid);
    },
    enabled: Boolean(tenantId && uuid),
  });
}
