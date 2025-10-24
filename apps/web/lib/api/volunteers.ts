import { apiFetch } from '@/lib/api/http';
import type { MemberSummary } from '@/lib/api/members';

export const VOLUNTEER_SIGNUP_STAGES = [
  { value: 'applied', label: 'New application' },
  { value: 'review', label: 'Under review' },
  { value: 'background_check', label: 'Background check' },
  { value: 'orientation', label: 'Orientation' },
  { value: 'ready', label: 'Ready to serve' },
  { value: 'inactive', label: 'Closed' },
] as const;

export interface VolunteerRole {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  skills_required?: string[] | null;
  teams?: VolunteerTeamSummary[];
  analytics?: {
    active_assignment_count?: number;
    pending_signup_count?: number;
    pipeline_stage_counts?: Array<{ stage: string; label: string; count: number }>;
  };
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

export type VolunteerSignupStage = (typeof VOLUNTEER_SIGNUP_STAGES)[number]['value'];

export interface VolunteerSignupStageHistoryEntry {
  from?: string | null;
  to: string;
  label: string;
  notes?: string | null;
  changed_by?: number | null;
  changed_at?: string;
}

export interface VolunteerSignup {
  id: number;
  tenant_id: number;
  volunteer_role_id?: number | null;
  volunteer_team_id?: number | null;
  member_id?: number | null;
  member?: MemberSummary | null;
  role?: VolunteerRoleSummary | null;
  team?: VolunteerTeam | null;
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  status: string;
  stage: VolunteerSignupStage;
  stage_label: string;
  applied_at?: string | null;
  reviewed_at?: string | null;
  confirmed_at?: string | null;
  last_contacted_at?: string | null;
  follow_up_at?: string | null;
  notes?: string | null;
  metadata?: Record<string, unknown> | null;
  stage_history?: VolunteerSignupStageHistoryEntry[] | null;
  onboarding_checklist?: Record<string, unknown> | null;
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

export async function fetchVolunteerSignups(
  tenantId: string,
  params: { status?: string; stage?: string; page?: number; per_page?: number } = {}
) {
  const searchParams = new URLSearchParams();
  if (params.status) searchParams.set('status', params.status);
  if (params.stage) searchParams.set('stage', params.stage);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  return apiFetch<PaginatedResponse<VolunteerSignup>>(
    `/v1/volunteer-signups${searchParams.size ? `?${searchParams}` : ''}`,
    {},
    tenantId
  );
}

export interface VolunteerSignupUpdatePayload {
  stage?: VolunteerSignupStage;
  stage_notes?: string;
  follow_up_at?: string | null;
  last_contacted_at?: string | null;
  onboarding_checklist?: Record<string, unknown> | null;
  status?: string;
  notes?: string;
  metadata?: Record<string, unknown> | null;
}

export async function updateVolunteerSignup(
  tenantId: string,
  id: number,
  payload: VolunteerSignupUpdatePayload
) {
  return apiFetch<VolunteerSignup>(
    `/v1/volunteer-signups/${id}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteVolunteerSignup(tenantId: string, id: number) {
  await apiFetch(`/v1/volunteer-signups/${id}`, { method: 'DELETE' }, tenantId);
}
