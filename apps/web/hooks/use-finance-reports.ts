'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createFinanceReport,
  fetchFinanceReports,
  FinanceReport,
  FinanceReportPayload,
  getFinanceReportDownloadUrl,
} from '@/lib/api/finance-reports';
import { useTenantId } from '@/lib/tenant';
import { useToast } from '@church/ui';

export function useFinanceReports() {
  const tenantId = useTenantId();

  return useQuery<FinanceReport[]>({
    queryKey: ['finance', 'reports', tenantId],
    queryFn: () => {
      if (!tenantId) {
        return Promise.resolve([]);
      }

      return fetchFinanceReports(tenantId);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateFinanceReport() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (payload: FinanceReportPayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return createFinanceReport(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finance', 'reports', tenantId] });
      pushToast({ title: 'Report queued', variant: 'success' });
    },
    onError: (error: unknown) => {
      const message = error instanceof Error ? error.message : 'Unable to queue report';
      pushToast({ title: 'Failed to queue report', description: message, variant: 'error' });
    },
  });
}

export function useFinanceReportDownload() {
  const tenantId = useTenantId();

  return (id: number) => {
    if (!tenantId) {
      throw new Error('Missing tenant context');
    }

    return getFinanceReportDownloadUrl(tenantId, id);
  };
}
