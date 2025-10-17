import { apiFetch } from '@/lib/api/http';

export interface VisitorWorkflowStep {
  id: number;
  workflow_id: number;
  step_number: number;
  name?: string | null;
  delay_minutes: number;
  channel: 'email' | 'sms' | 'task';
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
    channel: 'email' | 'sms' | 'task';
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
