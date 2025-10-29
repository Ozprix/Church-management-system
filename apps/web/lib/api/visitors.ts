import { apiFetch } from '@/lib/api/http';

export interface VisitorWorkflowStep {
  id: number;
  workflow_id: number;
  step_number: number;
  name?: string | null;
  delay_minutes: number;
  channel: 'email' | 'sms' | 'task' | 'staff_email';
  notification_template_id?: number | null;
  metadata?: Record<string, unknown> | null;
  is_active: boolean;
}

export interface VisitorWorkflow {
  id: number;
  name: string;
  description?: string | null;
  is_active: boolean;
  steps?: VisitorWorkflowStep[];
}

export interface VisitorFollowup {
  id: number;
  member_id: number;
  workflow_id: number;
  current_step_id?: number | null;
  status: 'pending' | 'in_progress' | 'completed' | 'halted';
  started_at?: string | null;
  next_run_at?: string | null;
  completed_at?: string | null;
  last_step_run_at?: string | null;
  workflow?: VisitorWorkflow;
  current_step?: VisitorWorkflowStep;
  logs_count?: number;
}

export interface VisitorFollowupLog {
  id: number;
  status: string;
  channel?: string | null;
  notes?: string | null;
  run_at?: string | null;
  step?: {
    id: number;
    name?: string | null;
    channel?: string | null;
    step_number: number;
  } | null;
  metadata?: Record<string, unknown> | null;
}

export interface VisitorAnalytics {
  stats: {
    total_visitors: number;
    converted_visitors: number;
    active_followups: number;
    completed_last_30_days: number;
    conversion_rate: number;
  };
  breakdown: {
    followup_statuses: Array<{ status: string; total: number }>;
    workflows: Array<{
      workflow_id: number;
      workflow_name: string;
      pending: number;
      in_progress: number;
      completed: number;
      halted: number;
    }>;
  };
  recent_activity: Array<{
    id: number;
    status: string;
    channel?: string | null;
    notes?: string | null;
    run_at?: string | null;
    step?: string | null;
    member?: {
      id: number;
      first_name: string;
      last_name: string;
    } | null;
  }>;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: unknown;
}

export async function fetchVisitorWorkflows(tenantId: string): Promise<VisitorWorkflow[]> {
  const response = await apiFetch<PaginatedResponse<VisitorWorkflow>>('/v1/visitor-workflows', {}, tenantId);
  return response.data;
}

export async function createVisitorWorkflow(
  tenantId: string,
  payload: { name: string; description?: string | null }
): Promise<VisitorWorkflow> {
  return apiFetch<VisitorWorkflow>(
    '/v1/visitor-workflows',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateVisitorWorkflow(
  tenantId: string,
  workflowId: number,
  payload: Partial<{ name: string; description?: string | null; is_active: boolean }>
): Promise<VisitorWorkflow> {
  return apiFetch<VisitorWorkflow>(
    `/v1/visitor-workflows/${workflowId}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteVisitorWorkflow(tenantId: string, workflowId: number): Promise<void> {
  await apiFetch(`/v1/visitor-workflows/${workflowId}`, { method: 'DELETE' }, tenantId);
}

export async function addVisitorWorkflowStep(
  tenantId: string,
  workflowId: number,
  payload: {
    step_number: number;
    name?: string | null;
    delay_minutes?: number;
    channel: 'email' | 'sms' | 'task' | 'staff_email';
    notification_template_id?: number | null;
    metadata?: Record<string, unknown> | null;
    is_active?: boolean;
  }
): Promise<VisitorWorkflowStep> {
  return apiFetch<VisitorWorkflowStep>(
    `/v1/visitor-workflows/${workflowId}/steps`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateVisitorWorkflowStep(
  tenantId: string,
  stepId: number,
  payload: Partial<{
    step_number: number;
    name?: string | null;
    delay_minutes?: number;
    channel: 'email' | 'sms' | 'task';
    notification_template_id?: number | null;
    metadata?: Record<string, unknown> | null;
    is_active?: boolean;
  }>
): Promise<VisitorWorkflowStep> {
  return apiFetch<VisitorWorkflowStep>(
    `/v1/visitor-workflow-steps/${stepId}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteVisitorWorkflowStep(tenantId: string, stepId: number): Promise<void> {
  await apiFetch(`/v1/visitor-workflow-steps/${stepId}`, { method: 'DELETE' }, tenantId);
}

export async function fetchVisitorFollowups(tenantId: string): Promise<VisitorFollowup[]> {
  const response = await apiFetch<PaginatedResponse<VisitorFollowup>>('/v1/visitor-followups', {}, tenantId);
  return response.data;
}

interface LogPaginatedResponse {
  data: VisitorFollowupLog[];
  meta?: unknown;
}

export async function fetchVisitorFollowupLogs(tenantId: string, followupId: number): Promise<VisitorFollowupLog[]> {
  const response = await apiFetch<LogPaginatedResponse>(`/v1/visitor-followups/${followupId}/logs`, {}, tenantId);
  return response.data;
}

export async function fetchVisitorAnalytics(tenantId: string): Promise<VisitorAnalytics> {
  const response = await apiFetch<{ data: VisitorAnalytics }>('/v1/visitors/analytics', {}, tenantId);
  return response.data;
}

export async function startVisitorFollowup(
  tenantId: string,
  payload: { member_id: number; workflow_id: number }
): Promise<VisitorFollowup> {
  return apiFetch<VisitorFollowup>(
    '/v1/visitor-followups',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function haltVisitorFollowup(
  tenantId: string,
  followupId: number
): Promise<VisitorFollowup> {
  return apiFetch<VisitorFollowup>(
    `/v1/visitor-followups/${followupId}`,
    {
      method: 'PATCH',
      body: JSON.stringify({ status: 'halted' }),
    },
    tenantId
  );
}
