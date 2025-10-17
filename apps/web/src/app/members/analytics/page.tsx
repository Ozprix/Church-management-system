"use client";

import { useMemo, useState } from "react";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch, buildApiUrl } from "@/lib/api";
import type {
  MemberAnalyticsResponse,
} from "@/types/member";
import { useQuery } from "@tanstack/react-query";
import { format, parseISO } from "date-fns";
import {
  AnalyticsAreaChartCard,
  AnalyticsBarChartCard,
  AnalyticsStatCard,
} from "@/components/analytics/widgets";

type Filters = {
  status: string;
  stage: string;
  withFamily: "any" | "true" | "false";
  joinedFrom: string;
  joinedTo: string;
};

const initialFilters: Filters = {
  status: "",
  stage: "",
  withFamily: "any",
  joinedFrom: "",
  joinedTo: "",
};

export default function MemberAnalyticsPage() {
  const { user, loading } = useAuth();
  const [filters, setFilters] = useState<Filters>(() => ({ ...initialFilters }));

  const filterParams = useMemo(() => {
    const params = new URLSearchParams();
    if (filters.status.trim()) params.set("status", filters.status.trim());
    if (filters.stage.trim()) params.set("stage", filters.stage.trim());
    if (filters.withFamily !== "any") params.set("with_family", filters.withFamily);
    if (filters.joinedFrom) params.set("joined_from", filters.joinedFrom);
    if (filters.joinedTo) params.set("joined_to", filters.joinedTo);
    return params.toString();
  }, [filters]);

  const analyticsQuery = useQuery<MemberAnalyticsResponse>({
    queryKey: ["member-analytics", filterParams],
    queryFn: async () => {
      const path = `/api/v1/members/analytics${
        filterParams ? `?${filterParams}` : ""
      }`;
      return apiFetch<MemberAnalyticsResponse>(path);
    },
    enabled: !!user,
    placeholderData: (previousData) => previousData,
  });

  const metrics = analyticsQuery.data;
  const isFetching = analyticsQuery.isFetching;
  const queryError = analyticsQuery.error as Error | null;

  const handleExport = () => {
    const url = buildApiUrl(
      `/api/v1/members/analytics/export${filterParams ? `?${filterParams}` : ""}`
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

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Member Analytics</h1>
        <p className="text-sm text-slate-600">
          View live KPIs, breakdowns by status/stage, and recent member additions.
        </p>
      </header>

      <section className="rounded border border-slate-200 p-4">
        <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <label className="text-sm font-medium text-slate-700">
            Status
            <input
              type="text"
              value={filters.status}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, status: event.target.value }))
              }
              placeholder="e.g. active"
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Stage
            <input
              type="text"
              value={filters.stage}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, stage: event.target.value }))
              }
              placeholder="e.g. onboarding"
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Family Association
            <select
              value={filters.withFamily}
              onChange={(event) =>
                setFilters((prev) => ({
                  ...prev,
                  withFamily: event.target.value as Filters["withFamily"],
                }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="any">Any</option>
              <option value="true">With family</option>
              <option value="false">Without family</option>
            </select>
          </label>
          <label className="text-sm font-medium text-slate-700">
            Joined from
            <input
              type="date"
              value={filters.joinedFrom}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, joinedFrom: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Joined to
            <input
              type="date"
              value={filters.joinedTo}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, joinedTo: event.target.value }))
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
          <section className="grid gap-4 sm:grid-cols-3">
            <AnalyticsStatCard
              title="Total members"
              value={metrics.totals.members}
              description="Filtered result count"
            />
            <AnalyticsStatCard
              title="Without family"
              value={metrics.totals.members_without_family}
              description="Members lacking household linkage"
            />
            <AnalyticsStatCard
              title="Stale profiles"
              value={metrics.totals.stale_profiles}
              description=">6 months since last update"
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-2">
            <AnalyticsBarChartCard
              title="By Status"
              data={metrics.by_status.map((row) => ({
                label: row.status,
                value: row.total,
              }))}
            />
            <AnalyticsBarChartCard
              title="Top Stages"
              data={metrics.by_stage.map((row) => ({
                label: row.stage,
                value: row.total,
              }))}
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <AnalyticsAreaChartCard
              title="New members (last 6 months)"
              data={metrics.new_members_trend.map((point) => ({
                label: point.label,
                value: point.total,
              }))}
            />
            <RecentMembersList members={metrics.recent_members} />
          </section>
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

function RecentMembersList({
  members,
}: {
  members: MemberAnalyticsResponse["recent_members"];
}) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">Recent Members</h3>
      <ul className="mt-4 space-y-3 text-sm text-slate-700">
        {members.map((member) => {
          const joinedLabel = member.joined_at
            ? format(parseISO(member.joined_at), "MMM d, yyyy")
            : "—";
          return (
            <li key={member.id} className="rounded border border-slate-100 p-3">
              <div className="font-medium text-slate-800">
                <a href={`/members/${member.uuid}`} className="hover:underline">
                  {member.name}
                </a>
              </div>
              <div className="text-xs text-slate-500">
                Status: {member.status} • Stage: {member.stage ?? "—"}
              </div>
              <div className="text-xs text-slate-500">Joined {joinedLabel}</div>
            </li>
          );
        })}
        {!members.length && (
          <p className="text-sm text-slate-500">No recent members in this range.</p>
        )}
      </ul>
    </section>
  );
}
