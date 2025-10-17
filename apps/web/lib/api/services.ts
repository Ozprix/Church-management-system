import { apiFetch } from '@/lib/api/http';

export interface ServiceSummary {
  id: number;
  name: string;
  slug: string;
  short_code?: string | null;
  description?: string | null;
  default_location?: string | null;
  default_start_time?: string | null;
  default_duration_minutes?: number | null;
  absence_threshold?: number | null;
  metadata?: Record<string, unknown> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ServiceFilters {
  search?: string;
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

export interface ServicePayload {
  name: string;
  slug?: string | null;
  short_code?: string | null;
  description?: string | null;
  default_location?: string | null;
  default_start_time?: string | null;
  default_duration_minutes?: number | null;
  absence_threshold?: number | null;
  metadata?: Record<string, unknown> | null;
}

export async function fetchServices(tenantId: string, filters: ServiceFilters = {}): Promise<PaginatedResponse<ServiceSummary>> {
  const params = new URLSearchParams();
  if (filters.search) params.set('search', filters.search);
  if (filters.page) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  return apiFetch<PaginatedResponse<ServiceSummary>>(`/v1/services${params.size ? `?${params}` : ''}`, {}, tenantId);
}

export async function createService(tenantId: string, payload: ServicePayload): Promise<ServiceSummary> {
  return apiFetch<ServiceSummary>(
    '/v1/services',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateService(tenantId: string, id: number, payload: Partial<ServicePayload>): Promise<ServiceSummary> {
  return apiFetch<ServiceSummary>(
    `/v1/services/${id}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}
