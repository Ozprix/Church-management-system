import { apiFetch } from '@/lib/api/http';
import type { ServiceSummary } from '@/lib/api/services';
import type { MemberSummary } from '@/lib/api/members';

export interface GatheringSummary {
  id: number;
  uuid: string;
  name: string;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
  starts_at?: string | null;
  ends_at?: string | null;
  location?: string | null;
  service?: ServiceSummary | null;
  attendance?: {
    total: number;
    present: number;
    absent: number;
    excused: number;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface GatheringDetail extends GatheringSummary {
  notes?: string | null;
  metadata?: Record<string, unknown> | null;
  attendance_records?: AttendanceRecord[];
}

export interface GatheringFilters {
  service_id?: number;
  status?: string;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export interface GatheringPayload {
  service_id?: number | null;
  name: string;
  starts_at: string;
  ends_at?: string | null;
  status?: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
  location?: string | null;
  notes?: string | null;
  metadata?: Record<string, unknown> | null;
}

export interface AttendanceRecord {
  id: number;
  status: 'present' | 'absent' | 'excused';
  check_in_method?: string | null;
  checked_in_at?: string | null;
  checked_out_at?: string | null;
  notes?: Record<string, unknown> | null;
  member?: MemberSummary | null;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
  };
}

export async function fetchGatherings(tenantId: string, filters: GatheringFilters = {}): Promise<PaginatedResponse<GatheringSummary>> {
  const params = new URLSearchParams();
  if (filters.service_id) params.set('service_id', String(filters.service_id));
  if (filters.status) params.set('status', filters.status);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.page) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  return apiFetch<PaginatedResponse<GatheringSummary>>(`/v1/gatherings${params.size ? `?${params}` : ''}`, {}, tenantId);
}

export async function fetchGathering(tenantId: string, uuid: string): Promise<GatheringDetail> {
  return apiFetch<GatheringDetail>(`/v1/gatherings/${uuid}`, {}, tenantId);
}

export async function createGathering(tenantId: string, payload: GatheringPayload): Promise<GatheringDetail> {
  return apiFetch<GatheringDetail>(
    '/v1/gatherings',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateGathering(tenantId: string, uuid: string, payload: Partial<GatheringPayload>): Promise<GatheringDetail> {
  return apiFetch<GatheringDetail>(
    `/v1/gatherings/${uuid}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteGathering(tenantId: string, uuid: string): Promise<void> {
  await apiFetch<unknown>(
    `/v1/gatherings/${uuid}`,
    {
      method: 'DELETE',
    },
    tenantId
  );
}

export interface AttendancePayload {
  member_id?: number;
  status?: 'present' | 'absent' | 'excused';
  check_in_method?: string | null;
  checked_in_at?: string | null;
  checked_out_at?: string | null;
  notes?: Record<string, unknown> | null;
}

export interface BulkAttendancePayload {
  member_ids: number[];
  status?: 'present' | 'absent' | 'excused';
}

export async function recordAttendance(tenantId: string, gatheringUuid: string, payload: AttendancePayload): Promise<AttendanceRecord> {
  return apiFetch<AttendanceRecord>(
    `/v1/gatherings/${gatheringUuid}/attendance`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateAttendance(tenantId: string, gatheringUuid: string, attendanceId: number, payload: AttendancePayload): Promise<AttendanceRecord> {
  return apiFetch<AttendanceRecord>(
    `/v1/gatherings/${gatheringUuid}/attendance/${attendanceId}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function bulkAttendance(tenantId: string, gatheringUuid: string, payload: BulkAttendancePayload): Promise<void> {
  await apiFetch(
    `/v1/gatherings/${gatheringUuid}/attendance/bulk`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function fetchAttendance(tenantId: string, gatheringUuid: string, params: { status?: string; page?: number; per_page?: number } = {}): Promise<PaginatedResponse<AttendanceRecord>> {
  const searchParams = new URLSearchParams();
  if (params.status) searchParams.set('status', params.status);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<AttendanceRecord>>(
    `/v1/gatherings/${gatheringUuid}/attendance${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}
