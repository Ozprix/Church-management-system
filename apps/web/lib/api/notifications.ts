import { apiFetch } from '@/lib/api/http';
import type { MemberSummary } from '@/lib/api/members';

export type NotificationChannel = 'sms' | 'email';
export type NotificationStatus = 'queued' | 'sending' | 'sent' | 'failed';

export interface NotificationTemplateSummary {
  id: number;
  name: string;
  slug: string;
  channel: NotificationChannel;
  subject?: string | null;
  body: string;
  placeholders?: string[] | null;
  metadata?: Record<string, unknown> | null;
}

export interface NotificationSummary {
  id: number;
  channel: NotificationChannel;
  recipient: string;
  subject?: string | null;
  body?: string | null;
  status: NotificationStatus;
  scheduled_for?: string | null;
  sent_at?: string | null;
  provider?: string | null;
  provider_message_id?: string | null;
  error_message?: string | null;
  attempts: number;
  payload?: Record<string, unknown> | null;
  template?: NotificationTemplateSummary | null;
  member?: MemberSummary | null;
  created_at?: string | null;
}

export interface NotificationFilters {
  status?: NotificationStatus;
  channel?: NotificationChannel;
  recipient?: string;
  page?: number;
  per_page?: number;
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

export async function fetchNotificationTemplates(
  tenantId: string,
  filters: { channel?: NotificationChannel; search?: string; page?: number; per_page?: number } = {}
) {
  const params = new URLSearchParams();
  if (filters.channel) params.set('channel', filters.channel);
  if (filters.search) params.set('search', filters.search);
  if (filters.page) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  return apiFetch<PaginatedResponse<NotificationTemplateSummary>>(
    `/v1/notification-templates${params.size ? `?${params}` : ''}`,
    {},
    tenantId
  );
}

export interface NotificationTemplatePayload {
  name: string;
  slug?: string | null;
  channel: NotificationChannel;
  subject?: string | null;
  body: string;
  placeholders?: string[];
  metadata?: Record<string, unknown> | null;
}

export async function createNotificationTemplate(
  tenantId: string,
  payload: NotificationTemplatePayload
): Promise<NotificationTemplateSummary> {
  return apiFetch<NotificationTemplateSummary>(
    '/v1/notification-templates',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateNotificationTemplate(
  tenantId: string,
  templateId: number,
  payload: Partial<NotificationTemplatePayload>
): Promise<NotificationTemplateSummary> {
  return apiFetch<NotificationTemplateSummary>(
    `/v1/notification-templates/${templateId}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteNotificationTemplate(tenantId: string, templateId: number): Promise<void> {
  await apiFetch(
    `/v1/notification-templates/${templateId}`,
    {
      method: 'DELETE',
    },
    tenantId
  );
}

export interface NotificationPayload {
  notification_template_id?: number;
  member_id?: number;
  channel?: NotificationChannel;
  recipient?: string;
  subject?: string | null;
  body?: string | null;
  payload?: Record<string, unknown> | null;
  scheduled_for?: string | null;
}

export async function fetchNotifications(tenantId: string, filters: NotificationFilters = {}) {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.channel) params.set('channel', filters.channel);
  if (filters.recipient) params.set('recipient', filters.recipient);
  if (filters.page) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  return apiFetch<PaginatedResponse<NotificationSummary>>(
    `/v1/notifications${params.size ? `?${params}` : ''}`,
    {},
    tenantId
  );
}

export async function createNotification(tenantId: string, payload: NotificationPayload) {
  return apiFetch<NotificationSummary>(
    '/v1/notifications',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function requeueNotification(tenantId: string, id: number, payload: Partial<NotificationPayload> = {}) {
  return apiFetch<NotificationSummary>(
    `/v1/notifications/${id}`,
    {
      method: 'PATCH',
      body: JSON.stringify({ status: 'queued', ...payload }),
    },
    tenantId
  );
}
