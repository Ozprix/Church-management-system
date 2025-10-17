'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchMemberCustomFields } from '@/lib/api/custom-fields';
import { useTenantId } from '@/lib/tenant';

export function useMemberCustomFields() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['member-custom-fields', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return [];
      }
      return fetchMemberCustomFields(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
