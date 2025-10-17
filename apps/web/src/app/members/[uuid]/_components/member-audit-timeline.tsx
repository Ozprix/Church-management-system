"use client";

import { useEffect, useState, useTransition } from "react";
import { apiFetch } from "@/lib/api";
import type { AuditLogEntry, PaginatedResponse } from "@/types/member";

interface MemberAuditTimelineProps {
  memberUuid: string;
}

export function MemberAuditTimeline({ memberUuid }: MemberAuditTimelineProps) {
  const [logs, setLogs] = useState<PaginatedResponse<AuditLogEntry> | null>(
    null
  );
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [memberUuid]);

  const refresh = () => {
    startTransition(async () => {
      try {
        const data = await apiFetch<PaginatedResponse<AuditLogEntry>>(
          `/api/v1/members/${memberUuid}/audits`
        );
        setLogs(data);
        setError(null);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : "Unable to load audit timeline right now."
        );
      }
    });
  };

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-slate-800">Activity</h2>
        <button
          onClick={refresh}
          disabled={isPending}
          className="rounded border px-3 py-1 text-xs uppercase tracking-wide text-slate-600 hover:bg-slate-100 disabled:opacity-50"
        >
          {isPending ? "Refreshing…" : "Refresh"}
        </button>
      </div>
      {error && (
        <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </div>
      )}
      <ol className="space-y-3">
        {(logs?.data ?? []).map((entry) => (
          <li key={entry.id} className="rounded border border-slate-200 p-4">
            <div className="flex items-center justify-between">
              <p className="font-medium text-slate-800">{entry.action}</p>
              <span className="text-xs text-slate-500">
                {entry.occurred_at
                  ? new Date(entry.occurred_at).toLocaleString()
                  : "—"}
              </span>
            </div>
            {entry.user && (
              <p className="text-xs text-slate-500">
                {entry.user.name} ({entry.user.email})
              </p>
            )}
            {entry.payload && (
              <pre className="mt-2 overflow-auto rounded bg-slate-100 p-2 text-xs text-slate-700">
                {JSON.stringify(entry.payload, null, 2)}
              </pre>
            )}
          </li>
        ))}
        {!logs?.data?.length && !error && (
          <p className="text-sm text-slate-500">No activity recorded yet.</p>
        )}
      </ol>
    </section>
  );
}
