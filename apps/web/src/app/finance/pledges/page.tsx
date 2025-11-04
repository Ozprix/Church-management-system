"use client";

import { useEffect, useMemo, useState } from "react";
import { useInfiniteQuery, useQuery } from "@tanstack/react-query";
import { format, parseISO } from "date-fns";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import type {
  PaginatedResponse,
  PledgeSummary,
  NotificationSummary,
} from "@/types/member";
import { PledgeList } from "@/components/finance/pledge-list";
import { ReminderHistory } from "@/components/finance/reminder-history";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

const cadenceOptions = [
  { value: "daily", label: "Daily" },
  { value: "weekly", label: "Weekly" },
  { value: "monthly", label: "Monthly" },
  { value: "quarterly", label: "Quarterly" },
];

const defaultCadence = "monthly";

type ReminderFormState = {
  reminderEnabled: boolean;
  reminderCadence: string;
};

type HistoryFilters = {
  search: string;
  channel: string;
  status: string;
};

function formatDateTime(value: string | null): string {
  if (!value) {
    return "—";
  }

  try {
    return format(parseISO(value), "MMM d, yyyy • p");
  } catch {
    return value;
  }
}

function formatCurrency(amount: string | null, currency: string | null): string {
  const parsed = amount ? Number.parseFloat(amount) : Number.NaN;
  if (Number.isNaN(parsed)) {
    return amount ?? "—";
  }

  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: currency || "USD",
    }).format(parsed);
  } catch {
    return `${parsed.toFixed(2)} ${currency ?? "USD"}`;
  }
}

export default function FinancePledgesPage(): React.ReactElement {
  const { user, loading } = useAuth();
  const [selectedPledgeId, setSelectedPledgeId] = useState<number | null>(() => {
    if (typeof window === "undefined") {
      return null;
    }
    const saved = window.localStorage.getItem("finance:selected-pledge-id");
    const parsed = saved ? Number.parseInt(saved, 10) : Number.NaN;
    return Number.isNaN(parsed) ? null : parsed;
  });
  const [formState, setFormState] = useState<ReminderFormState>({
    reminderEnabled: false,
    reminderCadence: defaultCadence,
  });
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [historyFilters, setHistoryFilters] = useState<HistoryFilters>({
    search: "",
    channel: "",
    status: "",
  });
  const [historyPreset, setHistoryPreset] = useState<string>("");

  const pledgesQuery = useQuery<PaginatedResponse<PledgeSummary>>({
    queryKey: ["finance-pledges"],
    queryFn: async () => apiFetch<PaginatedResponse<PledgeSummary>>("/api/v1/pledges?per_page=50"),
    enabled: !!user,
  });

  const pledges = pledgesQuery.data?.data ?? [];

  useEffect(() => {
    if (!selectedPledgeId && pledges.length > 0) {
      setSelectedPledgeId(pledges[0].id);
    }
  }, [pledges, selectedPledgeId]);

  const selectedPledge = useMemo(() => {
    const pledge = pledges.find((item) => item.id === selectedPledgeId);
    if (pledge && typeof window !== "undefined") {
      window.localStorage.setItem("finance:selected-pledge-id", String(pledge.id));
    }
    return pledge ?? null;
  }, [pledges, selectedPledgeId]);

  useEffect(() => {
    if (selectedPledge) {
      setFormState({
        reminderEnabled: selectedPledge.reminder_enabled,
        reminderCadence: selectedPledge.reminder_cadence ?? defaultCadence,
      });
      setSaveError(null);
    }
  }, [selectedPledge]);

  const reminderHistoryQuery = useInfiniteQuery<PaginatedResponse<NotificationSummary>>({
    queryKey: ["pledge-reminders", selectedPledgeId, historyFilters],
    queryFn: async ({ pageParam = 1, queryKey }) => {
      const [, pledgeId, filters] = queryKey as [string, number | null, HistoryFilters];
      if (!pledgeId) {
        return {
          data: [],
          meta: { total: 0, per_page: 20, current_page: 1, last_page: 1 },
        } as PaginatedResponse<NotificationSummary>;
      }

      const params = new URLSearchParams({ per_page: "20", page: String(pageParam) });
      if (filters.search) {
        params.set("q", filters.search);
      }
      if (filters.channel) {
        params.set("channel", filters.channel);
      }
      if (filters.status) {
        params.set("status", filters.status);
      }
      if (historyPreset === "last7") {
        params.set("since", new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString());
      }
      if (historyPreset === "failures") {
        params.set("status", "failed");
      }

      return apiFetch<PaginatedResponse<NotificationSummary>>(
        `/api/v1/pledges/${pledgeId}/reminders?${params.toString()}`
      );
    },
    enabled: !!user && !!selectedPledgeId,
    getNextPageParam: (lastPage) => {
      if (lastPage.meta.current_page < lastPage.meta.last_page) {
        return lastPage.meta.current_page + 1;
      }
      return undefined;
    },
  });

  const reminderEntries = useMemo(() => {
    if (!reminderHistoryQuery.data) {
      return [] as NotificationSummary[];
    }

    return reminderHistoryQuery.data.pages.flatMap((page) => page.data);
  }, [reminderHistoryQuery.data]);

  const outstandingAmount = useMemo(() => {
    if (!selectedPledge) {
      return null;
    }

    const amount = Number.parseFloat(selectedPledge.amount ?? "0");
    const fulfilled = Number.parseFloat(selectedPledge.fulfilled_amount ?? "0");
    if (Number.isNaN(amount) || Number.isNaN(fulfilled)) {
      return null;
    }

    return Math.max(amount - fulfilled, 0).toFixed(2);
  }, [selectedPledge]);

  const hasChanges = useMemo(() => {
    if (!selectedPledge) {
      return false;
    }

    if (formState.reminderEnabled !== selectedPledge.reminder_enabled) {
      return true;
    }

    if (!formState.reminderEnabled) {
      return false;
    }

    const currentCadence = selectedPledge.reminder_cadence ?? defaultCadence;
    return formState.reminderCadence !== currentCadence;
  }, [formState, selectedPledge]);

  const handleToggleReminder = (event: React.ChangeEvent<HTMLInputElement>) => {
    const enabled = event.target.checked;
    setFormState((prev) => ({
      reminderEnabled: enabled,
      reminderCadence: enabled ? prev.reminderCadence ?? defaultCadence : prev.reminderCadence,
    }));
  };

  const handleCadenceChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
    const cadence = event.target.value;
    setFormState((prev) => ({
      ...prev,
      reminderCadence: cadence,
    }));
  };

  const handleSaveReminder = async () => {
    if (!selectedPledge) {
      return;
    }

    setIsSaving(true);
    setSaveError(null);

    try {
      const payload: Record<string, unknown> = {
        reminder_enabled: formState.reminderEnabled,
      };

      if (formState.reminderEnabled) {
        payload.reminder_cadence = formState.reminderCadence;
      }

      await apiFetch(`/api/v1/pledges/${selectedPledge.id}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });

      await pledgesQuery.refetch();
      await reminderHistoryQuery.refetch();
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : "Unable to update reminder settings.");
    } finally {
      setIsSaving(false);
    }
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
          You must sign in to manage pledges.{" "}
          <a className="underline" href="/login">
            Go to login
          </a>
          .
        </p>
      </main>
    );
  }

  const pledgesError = pledgesQuery.error as Error | null;

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Pledge reminders</h1>
        <p className="text-sm text-slate-600">
          Toggle automated reminders, adjust cadence, and review recent reminder deliveries.
        </p>
        <div className="flex flex-wrap gap-2">
          <a
            href="/finance/analytics"
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            View finance analytics
          </a>
        </div>
      </header>

      {pledgesError && (
        <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {pledgesError.message}
        </div>
      )}

      <PledgeList
        pledges={pledges}
        selectedPledgeId={selectedPledgeId}
        onSelect={setSelectedPledgeId}
        isLoading={pledgesQuery.isLoading}
        title="Pledges"
        description="Select a pledge to adjust reminder cadence."
      />

      {selectedPledge && (
        <section className="grid gap-6 lg:grid-cols-[2fr_1fr]">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">
                Reminder settings • {selectedPledge.member
                  ? `${selectedPledge.member.first_name} ${selectedPledge.member.last_name}`
                  : "Unassigned member"}
              </CardTitle>
              <CardDescription>
                Fund: <span className="font-medium text-slate-700">{selectedPledge.fund?.name ?? "—"}</span>
              </CardDescription>
            </CardHeader>

            <CardContent>
              <dl className="grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                <div>
                  <dt className="text-slate-500">Pledge amount</dt>
                  <dd className="font-medium">
                    {formatCurrency(selectedPledge.amount, selectedPledge.currency)}
                  </dd>
              </div>
              <div>
                <dt className="text-slate-500">Outstanding balance</dt>
                <dd className="font-medium">
                  {outstandingAmount
                    ? formatCurrency(outstandingAmount, selectedPledge.currency)
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-slate-500">Last reminder sent</dt>
                <dd>{formatDateTime(selectedPledge.last_reminder_sent_at)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Next reminder scheduled</dt>
                <dd>{formatDateTime(selectedPledge.next_reminder_at)}</dd>
              </div>
            </dl>

              <form className="mt-6 space-y-4 rounded border border-slate-200 bg-slate-50 p-4">
                <label className="flex items-center gap-3 text-sm font-medium text-slate-700">
                  <input
                    type="checkbox"
                    checked={formState.reminderEnabled}
                    onChange={handleToggleReminder}
                  className="h-4 w-4 rounded border-slate-300"
                />
                Enable automatic reminders for this pledge
              </label>

              <label className="block text-sm font-medium text-slate-700">
                Reminder cadence
                <select
                  value={formState.reminderCadence}
                  onChange={handleCadenceChange}
                  disabled={!formState.reminderEnabled}
                  className="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-400"
                >
                  {cadenceOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>

                {saveError && (
                  <p className="text-sm text-red-600" role="alert">
                    {saveError}
                  </p>
                )}

                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={handleSaveReminder}
                    disabled={!hasChanges || isSaving}
                    className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {isSaving ? "Saving…" : "Save changes"}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    if (selectedPledge) {
                      setFormState({
                        reminderEnabled: selectedPledge.reminder_enabled,
                        reminderCadence: selectedPledge.reminder_cadence ?? defaultCadence,
                      });
                      setSaveError(null);
                    }
                  }}
                  disabled={!hasChanges || isSaving}
                  className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Reset
                </button>
              </div>
            </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-semibold">Reminder history</CardTitle>
              <CardDescription>
                Recent email and SMS reminders dispatched for this pledge.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <form className="grid gap-3 text-sm sm:grid-cols-3">
                <label className="flex flex-col gap-1">
                  <span className="font-medium text-slate-700">Search</span>
                  <input
                    type="search"
                    value={historyFilters.search}
                    onChange={(event) =>
                      setHistoryFilters((prev) => ({ ...prev, search: event.target.value }))
                    }
                    placeholder="Recipient, subject, body"
                    className="rounded border border-slate-300 px-3 py-2"
                  />
                </label>
                <label className="flex flex-col gap-1">
                  <span className="font-medium text-slate-700">Channel</span>
                  <select
                    value={historyFilters.channel}
                    onChange={(event) =>
                      setHistoryFilters((prev) => ({ ...prev, channel: event.target.value }))
                    }
                    className="rounded border border-slate-300 px-3 py-2"
                  >
                    <option value="">Any</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                  </select>
                </label>
                <label className="flex flex-col gap-1">
                  <span className="font-medium text-slate-700">Status</span>
                  <select
                    value={historyFilters.status}
                    onChange={(event) =>
                      setHistoryFilters((prev) => ({ ...prev, status: event.target.value }))
                    }
                    className="rounded border border-slate-300 px-3 py-2"
                  >
                    <option value="">Any</option>
                    <option value="queued">Queued</option>
                    <option value="sending">Sending</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                  </select>
                </label>
              </form>

              <ReminderHistory
                entries={reminderEntries}
                isLoading={reminderHistoryQuery.isLoading}
              />
            </CardContent>
            {reminderHistoryQuery.hasNextPage && (
              <CardFooter>
                <button
                  type="button"
                  onClick={() => reminderHistoryQuery.fetchNextPage()}
                  disabled={reminderHistoryQuery.isFetchingNextPage}
                  className="rounded border border-slate-300 px-3 py-2 text-xs text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {reminderHistoryQuery.isFetchingNextPage ? "Loading…" : "Load more"}
                </button>
              </CardFooter>
            )}
          </Card>
        </section>
      )}
    </main>
  );
}
