'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AttendancePayload,
  bulkAttendance,
  fetchAttendance,
  recordAttendance,
  updateAttendance,
  BulkAttendancePayload,
} from '@/lib/api/gatherings';
import { useTenantId } from '@/lib/tenant';

export function useAttendance(gatheringUuid: string | undefined, filters: { status?: string; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const params = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['attendance', tenantId, gatheringUuid, params],
    queryFn: async () => {
      if (!tenantId || !gatheringUuid) {
        return { data: [], meta: undefined };
      }
      return fetchAttendance(tenantId, gatheringUuid, params);
    },
    enabled: Boolean(tenantId && gatheringUuid),
  });
}

export function useRecordAttendance(gatheringUuid: string | undefined) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AttendancePayload) => {
      if (!tenantId || !gatheringUuid) throw new Error('Missing tenant or gathering');
      return recordAttendance(tenantId, gatheringUuid, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['gathering'] });
      queryClient.invalidateQueries({ queryKey: ['gatherings'] });
    },
  });
}

export function useUpdateAttendance(gatheringUuid: string | undefined) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ attendanceId, payload }: { attendanceId: number; payload: AttendancePayload }) => {
      if (!tenantId || !gatheringUuid) throw new Error('Missing tenant or gathering');
      return updateAttendance(tenantId, gatheringUuid, attendanceId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['gathering'] });
    },
  });
}

export function useBulkAttendance(gatheringUuid: string | undefined) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: BulkAttendancePayload) => {
      if (!tenantId || !gatheringUuid) throw new Error('Missing tenant or gathering');
      await bulkAttendance(tenantId, gatheringUuid, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['gathering'] });
      queryClient.invalidateQueries({ queryKey: ['gatherings'] });
    },
  });
}
