"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch, buildApiUrl } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import type { FamilyAnalyticsResponse } from "@/types/member";
import {
  AnalyticsBarChartCard,
  AnalyticsStatCard,
} from "@/components/analytics/widgets";
import { format, parseISO } from "date-fns";

type Filters = {
  minMembers: string;
  maxMembers: string;
  withPrimary: "any" | "true" | "false";
  city: string;
  state: string;
  createdFrom: string;
  createdTo: string;
};

const initialFilters: Filters = {
  minMembers: "",
  maxMembers: "",
  withPrimary: "any",
  city: "",
  state: "",
  createdFrom: "",
  createdTo: "",
};

export default function FamilyAnalyticsPage() {
  const { user, loading } = useAuth();
  const [filters, setFilters] = useState<Filters>(() => ({ ...initialFilters }));
  const router = useRouter();

  const filterParams = useMemo(() => {
    const params = new URLSearchParams();
    if (filters.minMembers) params.set("min_members", filters.minMembers);
    if (filters.maxMembers) params.set("max_members", filters.maxMembers);
    if (filters.withPrimary !== "any") params.set("with_primary_contact", filters.withPrimary);
    if (filters.city.trim()) params.set("city", filters.city.trim());
    if (filters.state.trim()) params.set("state", filters.state.trim());
    if (filters.createdFrom) params.set("created_from", filters.createdFrom);
    if (filters.createdTo) params.set("created_to", filters.createdTo);
    return params.toString();
  }, [filters]);

  const analyticsQuery = useQuery<FamilyAnalyticsResponse>({
    queryKey: ["family-analytics", filterParams],
    queryFn: async () => {
      const path = `/api/v1/families/analytics${
        filterParams ? `?${filterParams}` : ""
      }`;
      return apiFetch<FamilyAnalyticsResponse>(path);
    },
    enabled: !!user,
    placeholderData: (previousData) => previousData,
  });

  const metrics = analyticsQuery.data;
  const isFetching = analyticsQuery.isFetching;
  const queryError = analyticsQuery.error as Error | null;

  const handleExport = () => {
    const url = buildApiUrl(
      `/api/v1/families/analytics/export${filterParams ? `?${filterParams}` : ""}`
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
        <h1 className="text-2xl font-semibold text-slate-900">Family Analytics</h1>
        <p className="text-sm text-slate-600">
          Understand household composition and recent family registrations.
        </p>
      </header>

      <section className="rounded border border-slate-200 p-4">
        <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <label className="text-sm font-medium text-slate-700">
            Min members
            <input
              type="number"
              min={0}
              value={filters.minMembers}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, minMembers: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Max members
            <input
              type="number"
              min={0}
              value={filters.maxMembers}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, maxMembers: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Primary contact
            <select
              value={filters.withPrimary}
              onChange={(event) =>
                setFilters((prev) => ({
                  ...prev,
                  withPrimary: event.target.value as Filters["withPrimary"],
                }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="any">Any</option>
              <option value="true">Has primary contact</option>
              <option value="false">Missing primary contact</option>
            </select>
          </label>
          <label className="text-sm font-medium text-slate-700">
            City
            <input
              type="text"
              value={filters.city}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, city: event.target.value }))
              }
              placeholder="e.g. Riverdale"
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            State/Region
            <input
              type="text"
              value={filters.state}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, state: event.target.value }))
              }
              placeholder="e.g. CA"
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Created from
            <input
              type="date"
              value={filters.createdFrom}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, createdFrom: event.target.value }))
              }
              className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Created to
            <input
              type="date"
              value={filters.createdTo}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, createdTo: event.target.value }))
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
              title="Total families"
              value={metrics.totals.families}
              description="Filtered households"
            />
            <AnalyticsStatCard
              title="Avg. household size"
              value={metrics.totals.average_household_size.toFixed(1)}
              description="Members per family"
            />
            <AnalyticsStatCard
              title="Families with children"
              value={metrics.totals.families_with_children}
              description="Contains child relationship"
            />
            <AnalyticsStatCard
              title="Missing primary contact"
              value={metrics.totals.families_without_primary_contact}
              description="Requires follow-up"
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-2">
            <AnalyticsBarChartCard
              title="Household size distribution"
              data={metrics.size_distribution.map((entry) => ({
                label: entry.label,
                value: entry.total,
              }))}
              onBarClick={(datum) => {
                const label = datum.label;
                if (label === "1") {
                  setFilters((prev) => ({
                    ...prev,
                    minMembers: "1",
                    maxMembers: "1",
                  }));
                  return;
                }

                if (label === "2-3") {
                  setFilters((prev) => ({
                    ...prev,
                    minMembers: "2",
                    maxMembers: "3",
                  }));
                  return;
                }

                if (label === "4-5") {
                  setFilters((prev) => ({
                    ...prev,
                    minMembers: "4",
                    maxMembers: "5",
                  }));
                  return;
                }

                if (label === "6+") {
                  setFilters((prev) => ({
                    ...prev,
                    minMembers: "6",
                    maxMembers: "",
                  }));
                }
              }}
            />
            <AnalyticsBarChartCard
              title="Relationships represented"
              data={metrics.by_relationship.map((entry) => ({
                label: entry.relationship,
                value: entry.total,
              }))}
            />
          </section>

          <section className="grid gap-6 lg:grid-cols-2">
            <RecentFamiliesList
              families={metrics.recent_families}
              onViewFamily={(id) => router.push(`/families/${id}`)}
            />
            <FamiliesMissingPrimaryList
              families={metrics.families_missing_primary}
              onViewFamily={(id) => router.push(`/families/${id}`)}
            />
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

function RecentFamiliesList({
  families,
  onViewFamily,
}: {
  families: FamilyAnalyticsResponse["recent_families"];
  onViewFamily: (id: number) => void;
}) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">Recent families</h3>
      <ul className="mt-4 space-y-3 text-sm text-slate-700">
        {families.map((family) => {
          const createdLabel = family.created_at
            ? format(parseISO(family.created_at), "MMM d, yyyy")
            : "—";

          return (
            <li key={family.id} className="rounded border border-slate-100 p-3">
              <div className="font-medium text-slate-800">{family.family_name}</div>
              <div className="text-xs text-slate-500">
                Members: {family.members_count} • Created {createdLabel}
              </div>
              <div className="mt-2">
                <button
                  onClick={() => onViewFamily(family.id)}
                  className="rounded border border-slate-300 px-3 py-1 text-xs uppercase tracking-wide text-slate-600 hover:bg-slate-100"
                >
                  View family
                </button>
              </div>
            </li>
          );
        })}
        {!families.length && (
          <p className="text-sm text-slate-500">No recent families in this range.</p>
        )}
      </ul>
    </section>
  );
}

function FamiliesMissingPrimaryList({
  families,
  onViewFamily,
}: {
  families: FamilyAnalyticsResponse["families_missing_primary"];
  onViewFamily: (id: number) => void;
}) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">Needs primary contact</h3>
      <p className="text-xs text-slate-500">
        Prioritise these households to ensure someone is marked as the primary point of contact.
      </p>
      <ul className="mt-4 space-y-3 text-sm text-slate-700">
        {families.map((family) => {
          const createdLabel = family.created_at
            ? format(parseISO(family.created_at), "MMM d, yyyy")
            : "—";

          return (
            <li key={family.id} className="rounded border border-slate-100 p-3">
              <div className="font-medium text-slate-800">{family.family_name}</div>
              <div className="text-xs text-slate-500">
                Members: {family.members_count} • Created {createdLabel}
              </div>
              <div className="mt-2 flex flex-wrap gap-2">
                <button
                  onClick={() => onViewFamily(family.id)}
                  className="rounded border border-slate-300 px-3 py-1 text-xs uppercase tracking-wide text-slate-600 hover:bg-slate-100"
                >
                  View family
                </button>
              </div>
            </li>
          );
        })}
        {!families.length && (
          <p className="text-sm text-slate-500">All families have a primary contact – great job!</p>
        )}
      </ul>
    </section>
  );
}
