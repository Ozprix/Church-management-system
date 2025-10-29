/* eslint-disable @typescript-eslint/consistent-type-imports */
'use client';

import { useQuery } from '@tanstack/react-query';
import { AttendanceAnalyticsFilters, fetchAttendanceAnalytics } from '@/lib/api/attendance';
import { useTenantId } from '@/lib/tenant';

export function useAttendanceAnalytics(filters?: AttendanceAnalyticsFilters) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['attendance-analytics', tenantId, filters ?? {}],
    queryFn: async () => {
      if (!tenantId) {
        return null;
      }
      return fetchAttendanceAnalytics(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}
