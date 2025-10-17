import { apiFetch } from '@/lib/api/http';

export interface MemberSummary {
  id: number;
  uuid: string;
  first_name: string;
  last_name: string;
  preferred_name?: string | null;
  membership_status: string;
  membership_stage?: string | null;
  preferred_contact?: {
    id?: number;
    type: string;
    label?: string | null;
    value: string;
    is_primary?: boolean;
  } | null;
}

export interface MemberContact {
  id?: number;
  type: string;
  label?: string | null;
  value: string;
  is_primary?: boolean;
  is_emergency?: boolean;
  communication_preference?: string | null;
}

export interface MemberFamily {
  family_id: number;
  relationship?: string | null;
  is_primary_contact?: boolean;
  is_emergency_contact?: boolean;
}

export interface MemberCustomValue {
  field_id: number;
  value: unknown;
}

export interface MemberDetail extends MemberSummary {
  gender?: string | null;
  dob?: string | null;
  marital_status?: string | null;
  membership_stage?: string | null;
  joined_at?: string | null;
  photo_path?: string | null;
  notes?: string | null;
  contacts: MemberContact[];
  families: Array<{
    id: number;
    family_name: string;
    pivot?: {
      relationship?: string | null;
      is_primary_contact?: boolean;
      is_emergency_contact?: boolean;
    };
  }>;
  custom_values?: Array<{
    id: number;
    field_id: number;
    field?: {
      id: number;
      name: string;
      data_type: string;
    } | null;
    value: unknown;
    raw?: {
      value_string?: string | null;
      value_text?: string | null;
      value_number?: number | null;
      value_date?: string | null;
      value_boolean?: boolean | null;
      value_json?: unknown;
    };
  }>;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: unknown;
}

export interface MembersMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

export interface MembersResponse {
  data: MemberSummary[];
  meta?: MembersMeta;
}

export interface MemberAnalyticsResponse {
  totals: {
    members: number;
    members_without_family: number;
    stale_profiles: number;
  };
  by_status: Array<{ status: string; total: number }>;
  by_stage: Array<{ stage: string; total: number }>;
  new_members_trend: Array<{ label: string; total: number }>;
  recent_members: Array<{
    id: number;
    uuid: string;
    name: string;
    status?: string | null;
    stage?: string | null;
    joined_at: string | null;
  }>;
}

export interface MemberAnalyticsFilters {
  status?: string;
  stage?: string;
  joined_from?: string;
  joined_to?: string;
  with_family?: boolean | null;
}

export interface MemberFilters {
  search?: string;
  status?: string;
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: 'asc' | 'desc';
}

export async function fetchMembers(tenantId: string, filters: MemberFilters = {}): Promise<MembersResponse> {
  const params = new URLSearchParams();
  if (filters.search) {
    params.set('search', filters.search);
  }
  if (filters.status) {
    params.set('status', filters.status);
  }
  if (filters.page) {
    params.set('page', String(filters.page));
  }
  if (filters.per_page) {
    params.set('per_page', String(filters.per_page));
  }
  if (filters.sort) {
    params.set('sort', filters.sort);
  }
  if (filters.direction) {
    params.set('direction', filters.direction);
  }

  const query = params.toString();
  const response = await apiFetch<PaginatedResponse<MemberSummary>>(
    `/v1/members${query ? `?${query}` : ''}`,
    {},
    tenantId
  );
  return { data: response.data, meta: response.meta as MembersMeta | undefined };
}

export async function fetchMember(tenantId: string, uuid: string): Promise<MemberDetail> {
  return apiFetch<MemberDetail>(`/v1/members/${uuid}`, {}, tenantId);
}

export interface MemberPayload {
  first_name?: string;
  last_name?: string;
  preferred_name?: string | null;
  gender?: string | null;
  dob?: string | null;
  marital_status?: string | null;
  membership_status?: string;
  membership_stage?: string | null;
  joined_at?: string | null;
  notes?: string | null;
  contacts?: MemberContact[];
  families?: MemberFamily[];
  custom_values?: MemberCustomValue[];
}

export async function updateMember(tenantId: string, uuid: string, payload: MemberPayload): Promise<MemberDetail> {
  return apiFetch<MemberDetail>(
    `/v1/members/${uuid}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function createMember(tenantId: string, payload: MemberPayload): Promise<MemberDetail> {
  return apiFetch<MemberDetail>(
    '/v1/members',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export function buildMemberAnalyticsQuery(filters: MemberAnalyticsFilters = {}): string {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.stage) params.set('stage', filters.stage);
  if (filters.joined_from) params.set('joined_from', filters.joined_from);
  if (filters.joined_to) params.set('joined_to', filters.joined_to);
  if (typeof filters.with_family === 'boolean') {
    params.set('with_family', String(filters.with_family));
  }
  return params.size ? `?${params.toString()}` : '';
}

export async function fetchMemberAnalytics(
  tenantId: string,
  filters: MemberAnalyticsFilters = {}
): Promise<MemberAnalyticsResponse> {
  const query = buildMemberAnalyticsQuery(filters);
  return apiFetch<MemberAnalyticsResponse>(`/v1/members/analytics${query}`, {}, tenantId);
}

export function buildMemberAnalyticsExportUrl(filters: MemberAnalyticsFilters = {}): string {
  const query = buildMemberAnalyticsQuery(filters);
  return `/v1/members/analytics/export${query}`;
}
