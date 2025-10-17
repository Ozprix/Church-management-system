import { apiFetch } from '@/lib/api/http';
import type { MemberSummary } from '@/lib/api/members';

export interface VolunteerRole {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  skills_required?: string[] | null;
  teams?: VolunteerTeamSummary[];
}

export interface VolunteerTeamSummary {
  id: number;
  name: string;
  slug: string;
}

export interface VolunteerTeam extends VolunteerTeamSummary {
  description?: string | null;
  metadata?: Record<string, unknown> | null;
  roles?: VolunteerRoleSummary[];
}

export interface VolunteerRoleSummary {
  id: number;
  name: string;
  slug: string;
}

export interface VolunteerAssignment {
  id: number;
  status: 'scheduled' | 'confirmed' | 'swapped' | 'cancelled';
  starts_at?: string | null;
  ends_at?: string | null;
  notes?: Record<string, unknown> | null;
  member?: MemberSummary | null;
  role?: VolunteerRoleSummary | null;
  team?: VolunteerTeam | null;
  gathering?: {
    id: number;
    uuid: string;
    name: string;
    starts_at?: string | null;
  } | null;
  created_at?: string | null;
}

export interface VolunteerAvailability {
  id: number;
  member?: MemberSummary | null;
  weekdays?: string[] | null;
  time_blocks?: Array<{ start: string; end: string }> | null;
  unavailable_from?: string | null;
  unavailable_until?: string | null;
  notes?: string | null;
  updated_at?: string | null;
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

export async function fetchVolunteerRoles(tenantId: string, params: { search?: string; page?: number; per_page?: number } = {}) {
  const searchParams = new URLSearchParams();
  if (params.search) searchParams.set('search', params.search);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<VolunteerRole>>(
    `/v1/volunteer-roles${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}

export async function fetchVolunteerTeams(tenantId: string, params: { search?: string; page?: number; per_page?: number } = {}) {
  const searchParams = new URLSearchParams();
  if (params.search) searchParams.set('search', params.search);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<VolunteerTeam>>(
    `/v1/volunteer-teams${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}

export interface VolunteerAssignmentPayload {
  member_id: number;
  volunteer_role_id: number;
  volunteer_team_id?: number | null;
  gathering_id?: number | null;
  starts_at: string;
  ends_at?: string | null;
  status?: 'scheduled' | 'confirmed' | 'swapped' | 'cancelled';
  notes?: Record<string, unknown> | null;
}

export async function fetchVolunteerAssignments(tenantId: string, params: { member_id?: number; status?: string; page?: number; per_page?: number } = {}) {
  const searchParams = new URLSearchParams();
  if (params.member_id) searchParams.set('member_id', String(params.member_id));
  if (params.status) searchParams.set('status', params.status);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<VolunteerAssignment>>(
    `/v1/volunteer-assignments${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}

export async function createVolunteerAssignment(tenantId: string, payload: VolunteerAssignmentPayload) {
  return apiFetch<VolunteerAssignment>(
    '/v1/volunteer-assignments',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function swapVolunteerAssignments(tenantId: string, sourceId: number, targetId: number) {
  return apiFetch<{ message: string }>(
    `/v1/volunteer-assignments/${sourceId}/swap`,
    {
      method: 'POST',
      body: JSON.stringify({ target_assignment_id: targetId }),
    },
    tenantId
  );
}

export interface VolunteerAvailabilityPayload {
  tenant_id?: number;
  member_id: number;
  weekdays?: string[];
  time_blocks?: Array<{ start: string; end: string }>;
  unavailable_from?: string | null;
  unavailable_until?: string | null;
  notes?: string | null;
}

export async function fetchVolunteerAvailability(tenantId: string, params: { member_id?: number; page?: number; per_page?: number } = {}) {
  const searchParams = new URLSearchParams();
  if (params.member_id) searchParams.set('member_id', String(params.member_id));
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<VolunteerAvailability>>(
    `/v1/volunteer-availability${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}

export async function upsertVolunteerAvailability(tenantId: string, payload: VolunteerAvailabilityPayload) {
  return apiFetch<VolunteerAvailability>(
    '/v1/volunteer-availability',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}
