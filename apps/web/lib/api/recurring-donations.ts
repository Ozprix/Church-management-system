import { apiFetch } from '@/lib/api/http';

export type RecurringFrequency = 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'annually';
export type RecurringStatus = 'active' | 'paused' | 'cancelled';

export interface RecurringDonationSchedule {
  id: number;
  tenant_id?: number;
  member_id?: number | null;
  payment_method_id?: number | null;
  frequency: RecurringFrequency;
  amount: number;
  currency: string;
  status: RecurringStatus;
  starts_on: string;
  ends_on?: string | null;
  next_run_at?: string | null;
  metadata?: Record<string, unknown> | null;
  member?: {
    id: number;
    first_name?: string | null;
    last_name?: string | null;
  } | null;
  payment_method?: {
    id: number;
    type: string;
    brand?: string | null;
    last_four?: string | null;
  } | null;
}

export interface RecurringDonationAttempt {
  id: number;
  schedule_id: number;
  donation_id?: number | null;
  status: 'pending' | 'processing' | 'succeeded' | 'failed';
  amount: number;
  currency: string;
  provider?: string | null;
  provider_reference?: string | null;
  failure_reason?: string | null;
  processed_at?: string | null;
  created_at?: string | null;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    per_page?: number;
    last_page?: number;
    total?: number;
  };
}

export async function fetchRecurringDonationSchedules(
  tenantId: string,
  params: { status?: RecurringStatus; page?: number; per_page?: number } = {}
): Promise<PaginatedResponse<RecurringDonationSchedule>> {
  const searchParams = new URLSearchParams();
  if (params.status) searchParams.set('status', params.status);
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));

  const query = searchParams.size ? `?${searchParams.toString()}` : '';
  return apiFetch<PaginatedResponse<RecurringDonationSchedule>>(`/v1/recurring-donations${query}`, {}, tenantId);
}

export async function createRecurringDonationSchedule(
  tenantId: string,
  payload: {
    member_id?: number | null;
    payment_method_id?: number | null;
    frequency: RecurringFrequency;
    amount: number;
    currency?: string;
    starts_on: string;
    ends_on?: string | null;
  }
): Promise<RecurringDonationSchedule> {
  return apiFetch<RecurringDonationSchedule>(
    '/v1/recurring-donations',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateRecurringDonationSchedule(
  tenantId: string,
  id: number,
  payload: Partial<{
    member_id?: number | null;
    payment_method_id?: number | null;
    frequency: RecurringFrequency;
    amount: number;
    currency: string;
    status: RecurringStatus;
    starts_on: string;
    ends_on?: string | null;
  }>
): Promise<RecurringDonationSchedule> {
  return apiFetch<RecurringDonationSchedule>(
    `/v1/recurring-donations/${id}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteRecurringDonationSchedule(tenantId: string, id: number): Promise<void> {
  await apiFetch(`/v1/recurring-donations/${id}`, { method: 'DELETE' }, tenantId);
}

export async function fetchRecurringDonationAttempts(
  tenantId: string,
  scheduleId: number,
  params: { page?: number; per_page?: number } = {}
): Promise<PaginatedResponse<RecurringDonationAttempt>> {
  const searchParams = new URLSearchParams();
  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));
  const query = searchParams.size ? `?${searchParams.toString()}` : '';

  return apiFetch<PaginatedResponse<RecurringDonationAttempt>>(
    `/v1/recurring-donations/${scheduleId}/attempts${query}`,
    {},
    tenantId
  );
}
