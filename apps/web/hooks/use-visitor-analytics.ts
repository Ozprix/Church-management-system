'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchVisitorAnalytics, VisitorAnalytics } from '@/lib/api/visitors';
import { useTenantId } from '@/lib/tenant';

export function useVisitorAnalytics() {
  const tenantId = useTenantId();

  return useQuery<VisitorAnalytics>({
    queryKey: ['visitor-analytics', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return {
          stats: {
            total_visitors: 0,
            converted_visitors: 0,
            active_followups: 0,
            completed_last_30_days: 0,
            conversion_rate: 0,
          },
          breakdown: {
            followup_statuses: [],
            workflows: [],
          },
          recent_activity: [],
        };
      }

      return fetchVisitorAnalytics(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
