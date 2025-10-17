import { apiFetch } from '@/lib/api/http';

export interface PaymentMethod {
  id: number;
  tenant_id: number;
  member_id?: number | null;
  member?: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
  type: string;
  brand?: string | null;
  last_four?: string | null;
  provider?: string | null;
  provider_reference?: string | null;
  expires_at?: string | null;
  is_default: boolean;
  metadata?: Record<string, unknown> | null;
}

export interface PaymentMethodFilters {
  member_id?: number;
  type?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: unknown;
}

export async function fetchPaymentMethods(
  tenantId: string,
  filters: PaymentMethodFilters = {}
): Promise<PaginatedResponse<PaymentMethod>> {
  const params = new URLSearchParams();
  if (filters.member_id) {
    params.set('member_id', String(filters.member_id));
  }
  if (filters.type) {
    params.set('type', filters.type);
  }
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
  return apiFetch<PaginatedResponse<PaymentMethod>>(
    `/v1/payment-methods${query ? `?${query}` : ''}`,
    {},
    tenantId
  );
}

export interface PaymentMethodPayload {
  member_id?: number | null;
  type?: string;
  brand?: string | null;
  last_four?: string | null;
  provider?: string | null;
  provider_reference?: string | null;
  expires_at?: string | null;
  is_default?: boolean;
  metadata?: Record<string, unknown> | null;
}

export async function createPaymentMethod(
  tenantId: string,
  payload: PaymentMethodPayload
): Promise<PaymentMethod> {
  return apiFetch<PaymentMethod>(
    '/v1/payment-methods',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updatePaymentMethod(
  tenantId: string,
  id: number,
  payload: PaymentMethodPayload
): Promise<PaymentMethod> {
  return apiFetch<PaymentMethod>(
    `/v1/payment-methods/${id}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deletePaymentMethod(tenantId: string, id: number): Promise<void> {
  await apiFetch(`/v1/payment-methods/${id}`, { method: 'DELETE' }, tenantId);
}
