import { apiFetch } from '@/lib/api/http';

export interface MemberReportSummary {
  id: number;
  name: string;
  filters: Record<string, unknown> | null;
  frequency: 'none' | 'daily' | 'weekly' | 'monthly';
  channel: 'email' | 'download' | 'both';
  email_recipient?: string | null;
  last_run_at?: string | null;
  created_at?: string | null;
}

export interface MemberReportPayload {
  name: string;
  filters?: Record<string, unknown>;
  frequency?: 'none' | 'daily' | 'weekly' | 'monthly';
  channel?: 'email' | 'download' | 'both';
  email_recipient?: string | null;
}

export async function fetchMemberReports(tenantId: string): Promise<MemberReportSummary[]> {
  const response = await apiFetch<{ data: MemberReportSummary[] }>(
    '/v1/member-analytics-reports',
    {},
    tenantId
  );
  return response.data;
}

export async function createMemberReport(
  tenantId: string,
  payload: MemberReportPayload
): Promise<MemberReportSummary> {
  return apiFetch<MemberReportSummary>(
    '/v1/member-analytics-reports',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateMemberReport(
  tenantId: string,
  reportId: number,
  payload: MemberReportPayload
): Promise<MemberReportSummary> {
  return apiFetch<MemberReportSummary>(
    `/v1/member-analytics-reports/${reportId}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteMemberReport(tenantId: string, reportId: number): Promise<void> {
  await apiFetch(`/v1/member-analytics-reports/${reportId}`,
    {
      method: 'DELETE',
    },
    tenantId
  );
}

export async function runMemberReport(
  tenantId: string,
  reportId: number
): Promise<unknown> {
  return apiFetch(`/v1/member-analytics-reports/${reportId}/run`,
    {
      method: 'POST',
    },
    tenantId
  );
}

export function buildMemberReportExportUrl(reportId: number): string {
  return `/v1/member-analytics-reports/${reportId}/export`;
}
