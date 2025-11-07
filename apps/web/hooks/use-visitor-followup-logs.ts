'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchVisitorFollowupLogs, VisitorFollowupLog } from '@/lib/api/visitors';
import { useTenantId } from '@/lib/tenant';

export function useVisitorFollowupLogs(followupId: number | null | undefined) {
  const tenantId = useTenantId();

  return useQuery<VisitorFollowupLog[]>({
    queryKey: ['visitor-followup-logs', tenantId, followupId],
    queryFn: async () => {
      if (!tenantId || !followupId) {
        return [];
      }

      return fetchVisitorFollowupLogs(tenantId, followupId);
    },
    enabled: Boolean(tenantId && followupId),
  });
}
