export interface MemberContact {
  id: number;
  type: string;
  value: string;
  is_primary: boolean;
}

export interface MemberSummary {
  id: number;
  uuid: string;
  first_name: string;
  last_name: string;
  membership_status: string;
  membership_stage?: string | null;
  preferred_contact?: {
    value: string;
  } | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface MemberDetail extends MemberSummary {
  preferred_name?: string | null;
  gender?: string | null;
  dob?: string | null;
  contacts: MemberContact[];
  notes?: string | null;
}

export interface AuditLogEntry {
  id: number;
  action: string;
  auditable_type: string | null;
  auditable_id: number | null;
  user?: {
    id: number;
    name: string;
    email: string;
  } | null;
  payload: Record<string, unknown> | null;
  ip_address?: string | null;
  occurred_at?: string | null;
}

export interface MemberImportSummary {
  id: number;
  status: string;
  original_filename: string;
  total_rows: number;
  processed_rows: number;
  failed_rows: number;
  errors: Array<Record<string, unknown>> | null;
  completed_at: string | null;
  created_at: string;
}

export interface MemberAnalyticsTotals {
  members: number;
  members_without_family: number;
  stale_profiles: number;
}

export interface MemberAnalyticsStatusBreakdown {
  status: string;
  total: number;
}

export interface MemberAnalyticsStageBreakdown {
  stage: string;
  total: number;
}

export interface MemberAnalyticsTrendPoint {
  label: string;
  total: number;
}

export interface RecentMemberSummary {
  id: number;
  uuid: string;
  name: string;
  status: string;
  stage: string | null;
  joined_at: string | null;
}

export interface MemberAnalyticsResponse {
  totals: MemberAnalyticsTotals;
  by_status: MemberAnalyticsStatusBreakdown[];
  by_stage: MemberAnalyticsStageBreakdown[];
  new_members_trend: MemberAnalyticsTrendPoint[];
  recent_members: RecentMemberSummary[];
}

export interface FamilyAnalyticsTotals {
  families: number;
  average_household_size: number;
  families_with_children: number;
  families_without_primary_contact: number;
}

export interface FamilySizeDistributionEntry {
  label: string;
  total: number;
}

export interface FamilyRelationshipBreakdownEntry {
  relationship: string;
  total: number;
}

export interface RecentFamilySummary {
  id: number;
  family_name: string;
  members_count: number;
  created_at: string | null;
}

export interface FamilyAnalyticsResponse {
  totals: FamilyAnalyticsTotals;
  size_distribution: FamilySizeDistributionEntry[];
  by_relationship: FamilyRelationshipBreakdownEntry[];
  recent_families: RecentFamilySummary[];
}

export interface FinanceAnalyticsTotals {
  donations_amount: number;
  donations_this_month: number;
  average_donation: number;
  active_pledges: number;
}

export interface FinanceStatusBreakdownEntry {
  status: string;
  count: number;
  amount: number;
}

export interface FinanceFundBreakdownEntry {
  fund_id: number | null;
  fund_name: string;
  amount: number;
}

export interface FinanceTrendPoint {
  label: string;
  value: number;
}

export interface TopDonorSummary {
  member_id: number | null;
  member_name: string;
  total: number;
}

export interface RecentDonationSummary {
  id: number;
  amount: number;
  status: string;
  received_at: string | null;
  member_name: string;
  funds: string[];
}

export interface FinanceAnalyticsResponse {
  totals: FinanceAnalyticsTotals;
  by_status: FinanceStatusBreakdownEntry[];
  by_fund: FinanceFundBreakdownEntry[];
  donations_trend: FinanceTrendPoint[];
  top_donors: TopDonorSummary[];
  recent_donations: RecentDonationSummary[];
}

export interface FundSummary {
  id: number;
  name: string;
}
