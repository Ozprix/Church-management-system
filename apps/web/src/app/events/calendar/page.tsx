"use client";

import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { addMonths, endOfMonth, format, parseISO, startOfMonth, subMonths } from "date-fns";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import type { GatheringSummary } from "@/types/events";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

type CalendarResponse = {
  data: GatheringSummary[];
};

export default function EventsCalendarPage(): React.ReactElement {
  const { user, loading } = useAuth();
  const [currentMonth, setCurrentMonth] = useState(() => startOfMonth(new Date()));

  const rangeParams = useMemo(() => {
    const from = startOfMonth(currentMonth).toISOString();
    const to = endOfMonth(currentMonth).toISOString();
    return { from, to };
  }, [currentMonth]);

  const gatheringsQuery = useQuery<CalendarResponse>({
    queryKey: ["events-calendar", rangeParams.from, rangeParams.to],
    queryFn: async () =>
      apiFetch<CalendarResponse>(
        `/api/v1/gatherings/calendar?from=${encodeURIComponent(rangeParams.from)}&to=${encodeURIComponent(
          rangeParams.to
        )}`
      ),
    enabled: !!user,
    placeholderData: (previous) => previous,
  });

  const groupedByDay = useMemo(() => {
    const map = new Map<string, GatheringSummary[]>();
    const data = gatheringsQuery.data?.data ?? [];

    for (const gathering of data) {
      const dayKey = format(parseISO(gathering.starts_at), "yyyy-MM-dd");
      const existing = map.get(dayKey) ?? [];
      existing.push(gathering);
      map.set(dayKey, existing);
    }

    return Array.from(map.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([day, events]) => ({
        day,
        events: events.sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
      }));
  }, [gatheringsQuery.data]);

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
          You must sign in to view events.{" "}
          <a className="underline" href="/login">
            Go to login
          </a>
          .
        </p>
      </main>
    );
  }

  const isFetching = gatheringsQuery.isFetching;
  const error = gatheringsQuery.error as Error | null;

  const displayMonth = format(currentMonth, "MMMM yyyy");

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Events calendar</h1>
        <p className="text-sm text-slate-600">
          Browse upcoming gatherings, review conflicts, and drill into ticketing/registrations.
        </p>
      </header>

      <Card>
        <CardHeader className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <CardTitle className="text-lg">{displayMonth}</CardTitle>
            <CardDescription>
              Showing gatherings between {format(parseISO(rangeParams.from), "MMM d")} –{" "}
              {format(parseISO(rangeParams.to), "MMM d, yyyy")}.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setCurrentMonth((prev) => subMonths(prev, 1))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={() => setCurrentMonth(startOfMonth(new Date()))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Today
            </button>
            <button
              type="button"
              onClick={() => setCurrentMonth((prev) => addMonths(prev, 1))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Next
            </button>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {error && (
            <div className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {error.message}
            </div>
          )}

          {groupedByDay.length === 0 && !isFetching && (
            <p className="text-sm text-slate-500">No gatherings scheduled for this month.</p>
          )}

          {groupedByDay.map(({ day, events }) => (
            <section key={day} className="space-y-3">
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                {format(parseISO(day), "EEEE, MMMM d")}
              </h2>
              <div className="space-y-2">
                {events.map((event) => (
                  <div
                    key={event.id}
                    className="rounded border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="text-base font-semibold text-slate-900">
                          <a href={`/events/${event.uuid}`} className="hover:underline">
                            {event.name}
                          </a>
                        </p>
                        <p className="text-xs uppercase tracking-wide text-slate-500">
                          {event.status}
                        </p>
                      </div>
                      <div className="text-right text-sm text-slate-600">
                        <p>
                          {format(parseISO(event.starts_at), "h:mm a")} – {format(parseISO(event.ends_at), "h:mm a")}
                        </p>
                        <p>{event.location ?? "Location TBD"}</p>
                      </div>
                    </div>
                    {event.service && (
                      <p className="mt-2 text-sm text-slate-600">
                        Service: <span className="font-medium text-slate-800">{event.service.name}</span>
                      </p>
                    )}
                    <div className="mt-3 text-sm">
                      <a
                        href={`/events/${event.uuid}`}
                        className="rounded border border-slate-300 px-3 py-1 text-slate-700 hover:bg-slate-100"
                      >
                        Manage event
                      </a>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ))}

          {isFetching && (
            <p className="text-sm text-slate-500">Refreshing events…</p>
          )}
        </CardContent>
      </Card>
    </main>
  );
}
