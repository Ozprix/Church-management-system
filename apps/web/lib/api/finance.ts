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
