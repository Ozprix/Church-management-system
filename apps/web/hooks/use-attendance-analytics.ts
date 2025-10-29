/* eslint-disable @typescript-eslint/consistent-type-imports */
'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchAttendanceAnalytics } from '@/lib/api/attendance';
import { useTenantId } from '@/lib/tenant';

export function useAttendanceAnalytics() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['attendance-analytics', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }
      return fetchAttendanceAnalytics(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}
