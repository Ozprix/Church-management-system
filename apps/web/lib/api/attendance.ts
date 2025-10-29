import { apiFetch } from '@/lib/api/http';

export interface AttendanceSummary {
  gatherings_tracked: number;
  total_check_ins: number;
  attendance_rate: number;
  average_present: number;
}

export interface AttendanceTrendEntry {
  label: string;
  present: number;
  absent: number;
  excused: number;
}

export interface AttendanceTopGathering {
  id: number;
  uuid: string;
  name: string;
  service_id?: number | null;
  service?: string | null;
  starts_at?: string | null;
  present: number;
  attendance_rate: number;
}

export interface AttendanceFollowupCandidate {
  member_id: number;
  member_name: string;
  absent: number;
  present: number;
  total: number;
}

export interface AttendanceServiceBreakdown {
  service_id: number | null;
  service_name: string;
  gatherings_tracked: number;
  present: number;
  absent: number;
  excused: number;
}

export interface AttendanceMemberSegment {
  membership_status: string | null;
  label: string;
  members: number;
  present: number;
  absent: number;
  excused: number;
  total_records: number;
}

export interface AttendanceDepartmentSegment {
  department: string | null;
  label: string;
  members: number;
  present: number;
  absent: number;
  excused: number;
  total_records: number;
}

export interface AttendanceAgeBandSegment {
  band: string;
  label: string;
  members: number;
  present: number;
  absent: number;
  excused: number;
  total_records: number;
}

export interface AttendanceAnalytics {
  summary: AttendanceSummary;
  trend: AttendanceTrendEntry[];
  top_gatherings: AttendanceTopGathering[];
  followup_candidates: AttendanceFollowupCandidate[];
  service_breakdown: AttendanceServiceBreakdown[];
  member_segments: AttendanceMemberSegment[];
  department_segments: AttendanceDepartmentSegment[];
  age_bands: AttendanceAgeBandSegment[];
}

export type AttendanceAnalyticsFilters = {
  service_id?: number;
  status?: string;
  from?: string;
  to?: string;
};

function buildAttendanceQuery(filters?: AttendanceAnalyticsFilters): string {
  if (!filters) {
    return '';
  }

  const params = new URLSearchParams();
  if (filters.service_id) {
    params.set('service_id', String(filters.service_id));
  }
  if (filters.status) {
    params.set('status', filters.status);
  }
  if (filters.from) {
    params.set('from', filters.from);
  }
  if (filters.to) {
    params.set('to', filters.to);
  }

  return params.toString();
}

export async function fetchAttendanceAnalytics(
  tenantId: string,
  filters?: AttendanceAnalyticsFilters
): Promise<AttendanceAnalytics | null> {
  const query = buildAttendanceQuery(filters);
  const response = await apiFetch<{ data: AttendanceAnalytics }>(
    `/v1/attendance/analytics${query ? `?${query}` : ''}`,
    {},
    tenantId
  );
  return response.data ?? null;
}

export function buildAttendanceAnalyticsExportUrl(filters?: AttendanceAnalyticsFilters): string {
  const query = buildAttendanceQuery(filters);
  return `/v1/attendance/analytics/export${query ? `?${query}` : ''}`;
}

export function buildGatheringAttendanceExportUrl(uuid: string, format: 'csv' | 'pdf' = 'csv'): string {
  if (format === 'csv') {
    return `/v1/gatherings/${uuid}/attendance/export`;
  }

  const params = new URLSearchParams({ format });
  return `/v1/gatherings/${uuid}/attendance/export?${params.toString()}`;
}

export function buildAttendanceBulkExportUrl(): string {
  return '/v1/attendance/exports';
}
