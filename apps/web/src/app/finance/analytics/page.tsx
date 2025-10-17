"use client";

import { useMemo, useState } from "react";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch, buildApiUrl } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import type {
  FinanceAnalyticsResponse,
  FundSummary,
} from "@/types/member";
import {
  AnalyticsAreaChartCard,
  AnalyticsBarChartCard,
  AnalyticsStatCard,
} from "@/components/analytics/widgets";
import { format, parseISO } from "date-fns";

type Filters = {
  status: string;
  fundId: string;
  dateFrom: string;
  dateTo: string;
};

const initialFilters: Filters = {
  status: "",
  fundId: "",
  dateFrom: "",
  dateTo: "",
};

const donationStatusOptions = ["", "succeeded", "pending", "failed", "refunded"];

export default function FinanceAnalyticsPage() {
  const { user, loading } = useAuth();
  const [filters, setFilters] = useState<Filters>(() => ({ ...initialFilters }));

  const filterParams = useMemo(() => {
    const params = new URLSearchParams();
    if (filters.status) params.set("status", filters.status);
    if (filters.fundId) params.set("fund_id", filters.fundId);
    if (filters.dateFrom) params.set("date_from", filters.dateFrom);
    if (filters.dateTo) params.set("date_to", filters.dateTo);
    return params.toString();
  }, [filters]);

  const fundsQuery = useQuery<FundSummary[]>({
    queryKey: ["funds"],
    queryFn: async () => {
      const response = await apiFetch<{ data: FundSummary[] }>(
        "/api/v1/funds?per_page=100"
      );
      return response.data;
    },
    enabled: !!user,
    staleTime: 5 * 60 * 1000,
  });

  const analyticsQuery = useQuery<FinanceAnalyticsResponse>({
    queryKey: ["finance-analytics", filterParams],
    queryFn: async () => {
      const path = `/api/v1/finance/analytics${
        filterParams ? `?${filterParams}` : ""
      }`;
      return apiFetch<FinanceAnalyticsResponse>(path);
    },
    enabled: !!user,
    placeholderData: (previousData) => previousData,
  });

  const metrics = analyticsQuery.data;
  const isFetching = analyticsQuery.isFetching;
  const queryError = analyticsQuery.error as Error | null;

  const handleExport = () => {
    const url = buildApiUrl(
      `/api/v1/finance/analytics/export${filterParams ? `?${filterParams}` : ""}`
    );
    window.open(url, "_blank", "noopener,noreferrer");
  };

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading…</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">
          You must sign in to view analytics. <a className="underline" href="/login">Go to login</a>.
        </p>
      </main>
    );
  }

  const errorMessage = queryError?.message ?? null;
  const funds = fundsQuery.data ?? [];

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Finance Analytics</h1>
        <p className="text-sm text-slate-600">
          Monitor giving trends, fund performance, and donor engagement.
        </p>
      </header>

      <section className="rounded border border-slate-200 p-4">
        <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <label className="text-sm font-medium text-slate-700">
            Status
            <select
              value={filters.status}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, status: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
              {donationStatusOptions.map((status) => (
                <option key={status} value={status}>
                  {status ? status : "Any"}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-medium text-slate-700">
            Fund
            <select
              value={filters.fundId}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, fundId: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">All funds</option>
              {funds.map((fund) => (
                <option key={fund.id} value={fund.id}>
                  {fund.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-medium text-slate-700">
            Date from
            <input
              type="date"
              value={filters.dateFrom}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, dateFrom: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Date to
            <input
              type="date"
              value={filters.dateTo}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, dateTo: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
        </form>
        <div className="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilters({ ...initialFilters })}
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            Clear filters
          </button>
          <button
            type="button"
            onClick={() => analyticsQuery.refetch()}
            disabled={isFetching}
            className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
          >
            {isFetching ? "Refreshing…" : "Refresh"}
          </button>
          <button
            type="button"
            onClick={handleExport}
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            Export CSV
          </button>
        </div>
      </section>

      {errorMessage && (
        <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {errorMessage}
        </div>
      )}

      {metrics ? (
        <div className="space-y-8">
          <section className="grid gap-4 sm:grid-cols-4">
            <AnalyticsStatCard
              title="Total donations"
              value={`$${metrics.totals.donations_amount.toFixed(2)}`}
              description="Sum of succeeded donations"
            />
            <AnalyticsStatCard
              title="This month"
              value={`$${metrics.totals.donations_this_month.toFixed(2)}`}
              description="Succeeded donations since month start"
            />
            <AnalyticsStatCard
              title="Average donation"
              value={`$${metrics.totals.average_donation.toFixed(2)}`}
              description="Across filtered donations"
            />
            <AnalyticsStatCard
              title="Active pledges"
              value={metrics.totals.active_pledges}
              description="Currently active commitments"
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-2">
            <AnalyticsBarChartCard
              title="Donations by status"
              data={metrics.by_status.map((entry) => ({
                label: entry.status,
                value: entry.amount,
              }))}
            />
            <AnalyticsBarChartCard
              title="Funds supported"
              data={metrics.by_fund.map((entry) => ({
                label: entry.fund_name,
                value: entry.amount,
              }))}
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <AnalyticsAreaChartCard
              title="Donations (last 6 months)"
              data={metrics.donations_trend.map((point) => ({
                label: point.label,
                value: point.value,
              }))}
            />
            <TopDonorsList donors={metrics.top_donors} />
          </section>

          <RecentDonationsList donations={metrics.recent_donations} />
        </div>
      ) : (
        <div className="flex min-h-[200px] items-center justify-center rounded border border-slate-200">
          <p className="text-sm text-slate-500">
            {isFetching ? "Loading analytics…" : "No analytics available yet."}
          </p>
        </div>
      )}
    </main>
  );
}

function TopDonorsList({
  donors,
}: {
  donors: FinanceAnalyticsResponse["top_donors"];
}) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">Top donors</h3>
      <ul className="mt-4 space-y-3 text-sm text-slate-700">
        {donors.map((donor) => (
          <li key={`${donor.member_id ?? "anonymous"}-${donor.member_name}`} className="flex items-center justify-between rounded border border-slate-100 px-3 py-2">
            <span>{donor.member_name}</span>
            <span className="text-slate-500">${donor.total.toFixed(2)}</span>
          </li>
        ))}
        {!donors.length && (
          <p className="text-sm text-slate-500">No donors in this range.</p>
        )}
      </ul>
    </section>
  );
}

function RecentDonationsList({
  donations,
}: {
  donations: FinanceAnalyticsResponse["recent_donations"];
}) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">Recent donations</h3>
      <ul className="mt-4 space-y-3 text-sm text-slate-700">
        {donations.map((donation) => {
          const receivedLabel = donation.received_at
            ? format(parseISO(donation.received_at), "MMM d, yyyy")
            : "—";

          return (
            <li key={donation.id} className="rounded border border-slate-100 p-3">
              <div className="flex justify-between text-slate-800">
                <span>{donation.member_name}</span>
                <span>${donation.amount.toFixed(2)}</span>
              </div>
              <div className="mt-1 text-xs text-slate-500">
                {donation.status} • {receivedLabel} • Funds: {donation.funds.join(", ") || "—"}
              </div>
            </li>
          );
        })}
        {!donations.length && (
          <p className="text-sm text-slate-500">No donations recorded in this range.</p>
        )}
      </ul>
    </section>
  );
}
