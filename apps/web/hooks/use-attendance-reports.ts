/* eslint-disable @typescript-eslint/consistent-type-imports */
'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AttendanceReportPayload,
  AttendanceReportSummary,
  createAttendanceReport,
  deleteAttendanceReport,
  fetchAttendanceReports,
  runAttendanceReport,
  updateAttendanceReport,
} from '@/lib/api/attendance-reports';
import { useTenantId } from '@/lib/tenant';

export function useAttendanceReports() {
  const tenantId = useTenantId();

  return useQuery<AttendanceReportSummary[]>({
    queryKey: ['attendance-reports', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return [];
      }
      return fetchAttendanceReports(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateAttendanceReport() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AttendanceReportPayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }
      return createAttendanceReport(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-reports', tenantId] });
    },
  });
}

export function useUpdateAttendanceReport(reportId: number | null) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AttendanceReportPayload) => {
      if (!tenantId || !reportId) {
        throw new Error('Missing tenant context or report');
      }
      return updateAttendanceReport(tenantId, reportId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-reports', tenantId] });
    },
  });
}

export function useDeleteAttendanceReport() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (reportId: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }
      return deleteAttendanceReport(tenantId, reportId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-reports', tenantId] });
    },
  });
}

export function useRunAttendanceReport() {
  const tenantId = useTenantId();

  return useMutation({
    mutationFn: async (reportId: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }
      return runAttendanceReport(tenantId, reportId);
    },
  });
}
