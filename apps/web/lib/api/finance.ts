import { apiFetch } from '@/lib/api/http';

export interface FinanceTotals {
  donations: number;
  month_to_date: number;
  average_donation: number;
  active_pledges: number;
  fulfilled_pledges: number;
}

export interface RecurringPledgeSummary {
  id: number;
  amount: number;
  fulfilled_amount: number;
  frequency: string;
}

export interface TopFundSummary {
  fund_id: number;
  fund_name?: string | null;
  total_amount: number;
}

export interface DonationItemSummary {
  id?: number;
  fund?: {
    id: number;
    name: string;
  } | null;
  amount: number;
}

export interface DonationSummary {
  id: number;
  amount: string | number;
  currency: string;
  status: string;
  received_at?: string | null;
  provider?: string | null;
  member?: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
  items?: DonationItemSummary[];
}

export interface FinanceDashboardResponse {
  totals: FinanceTotals;
  recurring_pledges: RecurringPledgeSummary[];
  top_funds: TopFundSummary[];
  recent_donations: DonationSummary[];
}

export async function fetchFinanceDashboard(tenantId: string): Promise<FinanceDashboardResponse> {
  return apiFetch<FinanceDashboardResponse>('/v1/finance/dashboard', {}, tenantId);
}

export interface FinanceAnalyticsResponse {
  totals: {
    donations_amount: number;
    donations_this_month: number;
    average_donation: number;
    active_pledges: number;
  };
  by_status: Array<{ status: string; count: number; amount: number }>;
  by_fund: Array<{ fund_id: number | null; fund_name?: string | null; amount: number }>;
  donations_trend: Array<{ label: string; value: number }>;
  top_donors: Array<{ member_id?: number | null; member_name: string; total: number }>;
  recent_donations: Array<{
    id: number;
    amount: number;
    status: string;
    received_at?: string | null;
    member_name?: string;
    funds: string[];
  }>;
  filters?: {
    statuses: string[];
    funds: Array<{ id: number; name: string }>;
    date_range?: {
      earliest?: string | null;
      latest?: string | null;
    };
  };
}

export interface FinanceAnalyticsFilters {
  status?: string;
  fund_id?: number | null;
  date_from?: string;
  date_to?: string;
}

export function buildFinanceAnalyticsQuery(filters: FinanceAnalyticsFilters = {}): string {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.fund_id) params.set('fund_id', String(filters.fund_id));
  if (filters.date_from) params.set('date_from', filters.date_from);
  if (filters.date_to) params.set('date_to', filters.date_to);

  return params.size ? `?${params.toString()}` : '';
}

export async function fetchFinanceAnalytics(
  tenantId: string,
  filters: FinanceAnalyticsFilters = {}
): Promise<FinanceAnalyticsResponse> {
  const query = buildFinanceAnalyticsQuery(filters);
  return apiFetch<FinanceAnalyticsResponse>(`/v1/finance/analytics${query}`, {}, tenantId);
}

export function buildFinanceAnalyticsExportUrl(filters: FinanceAnalyticsFilters = {}): string {
  const query = buildFinanceAnalyticsQuery(filters);
  return `/v1/finance/analytics/export${query}`;
}
