import { apiFetch } from '@/lib/api/http';

export interface FamilySummary {
  id: number;
  family_name: string;
  members_count?: number;
  address?: Record<string, unknown> | null;
  created_at?: string | null;
}

export interface FamilyDetail extends FamilySummary {
  notes?: string | null;
  address?: Record<string, unknown> | null;
  members: Array<{
    id: number;
    uuid: string;
    first_name: string;
    last_name: string;
    pivot?: {
      relationship?: string | null;
      is_primary_contact?: boolean;
      is_emergency_contact?: boolean;
    };
  }>;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

export interface FamilyFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

export async function fetchFamilies(
  tenantId: string,
  filters: FamilyFilters = {}
): Promise<PaginatedResponse<FamilySummary>> {
  const params = new URLSearchParams();
  if (filters.search) {
    params.set('search', filters.search);
  }
  if (filters.page) {
    params.set('page', String(filters.page));
  }
  if (filters.per_page) {
    params.set('per_page', String(filters.per_page));
  }

  const query = params.toString();
  return apiFetch<PaginatedResponse<FamilySummary>>(
    `/v1/families${query ? `?${query}` : ''}`,
    {},
    tenantId
  );
}

export async function fetchFamily(tenantId: string, id: number): Promise<FamilyDetail> {
  return apiFetch<FamilyDetail>(`/v1/families/${id}`, {}, tenantId);
}

export interface FamilyMemberPayload {
  member_id: number;
  relationship?: string | null;
  is_primary_contact?: boolean;
  is_emergency_contact?: boolean;
}

export interface FamilyPayload {
  family_name?: string;
  notes?: string | null;
  address?: Record<string, unknown> | null;
  members?: FamilyMemberPayload[];
}

export interface FamilyAnalyticsResponse {
  totals: {
    families: number;
    average_household_size: number;
    families_with_children: number;
    families_without_primary_contact: number;
  };
  size_distribution: Array<{ label: string; total: number }>;
  by_relationship: Array<{ relationship: string; total: number }>;
  recent_families: Array<{
    id: number;
    family_name: string;
    members_count: number;
    created_at: string | null;
  }>;
  families_missing_primary: Array<{
    id: number;
    family_name: string;
    members_count: number;
    created_at: string | null;
  }>;
  filters?: {
    cities: string[];
    states: string[];
    created_range?: {
      earliest?: string | null;
      latest?: string | null;
    };
  };
}

export interface FamilyAnalyticsFilters {
  min_members?: number | null;
  max_members?: number | null;
  with_primary_contact?: boolean | null;
  city?: string;
  state?: string;
  created_from?: string;
  created_to?: string;
}

export interface FamilyDashboardResponse {
  stats: {
    total_families: number;
    total_individuals: number;
    families_with_primary_contact: number;
    families_without_primary_contact: number;
    families_with_emergency_contact: number;
  };
  recent_families: Array<{
    id: number;
    family_name: string;
    members_count: number;
    created_at: string | null;
  }>;
  by_city: Array<{
    city: string;
    total: number;
  }>;
  new_families_trend: Array<{
    label: string;
    total: number;
  }>;
  reminders: {
    missing_primary_contact_total: number;
    missing_emergency_contact_total: number;
    suggested_families: Array<{
      id: number;
      family_name: string;
      members_count: number;
    }>;
  };
  upcoming_anniversaries: Array<{
    id: number;
    family_name: string;
    anniversary_on: string | null;
    days_until: number;
  }>;
}

export async function createFamily(tenantId: string, payload: FamilyPayload): Promise<FamilyDetail> {
  return apiFetch<FamilyDetail>(
    '/v1/families',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateFamily(tenantId: string, id: number, payload: FamilyPayload): Promise<FamilyDetail> {
  return apiFetch<FamilyDetail>(
    `/v1/families/${id}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function fetchFamilyDashboard(tenantId: string): Promise<FamilyDashboardResponse> {
  return apiFetch<FamilyDashboardResponse>('/v1/families/dashboard', {}, tenantId);
}

export function buildFamilyAnalyticsQuery(filters: FamilyAnalyticsFilters = {}): string {
  const params = new URLSearchParams();
  if (filters.min_members !== null && filters.min_members !== undefined) {
    params.set('min_members', String(filters.min_members));
  }
  if (filters.max_members !== null && filters.max_members !== undefined) {
    params.set('max_members', String(filters.max_members));
  }
  if (typeof filters.with_primary_contact === 'boolean') {
    params.set('with_primary_contact', String(filters.with_primary_contact));
  }
  if (filters.city) {
    params.set('city', filters.city);
  }
  if (filters.state) {
    params.set('state', filters.state);
  }
  if (filters.created_from) {
    params.set('created_from', filters.created_from);
  }
  if (filters.created_to) {
    params.set('created_to', filters.created_to);
  }

  return params.size ? `?${params.toString()}` : '';
}

export async function fetchFamilyAnalytics(
  tenantId: string,
  filters: FamilyAnalyticsFilters = {}
): Promise<FamilyAnalyticsResponse> {
  const query = buildFamilyAnalyticsQuery(filters);
  return apiFetch<FamilyAnalyticsResponse>(`/v1/families/analytics${query}`, {}, tenantId);
}

export function buildFamilyAnalyticsExportUrl(filters: FamilyAnalyticsFilters = {}): string {
  const query = buildFamilyAnalyticsQuery(filters);
  return `/v1/families/analytics/export${query}`;
}
