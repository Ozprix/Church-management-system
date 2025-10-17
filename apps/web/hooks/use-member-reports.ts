'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createMemberReport,
  deleteMemberReport,
  fetchMemberReports,
  MemberReportPayload,
  updateMemberReport,
} from '@/lib/api/member-reports';
import { useTenantId } from '@/lib/tenant';

export function useMemberReports() {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['member-reports', tenantId],
    queryFn: async () => {
      if (!tenantId) {
        return [];
      }
      return fetchMemberReports(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateMemberReport() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: MemberReportPayload) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return createMemberReport(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['member-reports'] });
    },
  });
}

export function useUpdateMemberReport(reportId: number | null) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: MemberReportPayload) => {
      if (!tenantId || reportId === null) throw new Error('Missing report id');
      return updateMemberReport(tenantId, reportId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['member-reports'] });
    },
  });
}

export function useDeleteMemberReport() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (reportId: number) => {
      if (!tenantId) throw new Error('Missing tenant id');
      await deleteMemberReport(tenantId, reportId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['member-reports'] });
    },
  });
}
