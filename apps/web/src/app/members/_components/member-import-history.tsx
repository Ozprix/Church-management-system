"use client";

import { useEffect, useState, useTransition } from "react";
import { apiFetch } from "@/lib/api";
import type {
  MemberImportSummary,
  PaginatedResponse,
} from "@/types/member";

export function MemberImportHistory() {
  const [imports, setImports] = useState<PaginatedResponse<MemberImportSummary> | null>(
    null
  );
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const refresh = () => {
    startTransition(async () => {
      try {
        const data = await apiFetch<PaginatedResponse<MemberImportSummary>>(
          "/api/v1/member-imports"
        );
        setImports(data);
        setError(null);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : "Unable to load import history right now."
        );
      }
    });
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-slate-800">
          Recent Imports
        </h3>
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
      <div className="space-y-2">
        {(imports?.data ?? []).map((importJob) => (
          <article
            key={importJob.id}
            className="rounded border border-slate-200 p-3 text-sm"
          >
            <div className="flex items-center justify-between">
              <div className="font-medium text-slate-800">
                {importJob.original_filename}
              </div>
              <span className="rounded bg-slate-100 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-600">
                {importJob.status}
              </span>
            </div>
            <div className="mt-1 text-slate-600">
              {importJob.processed_rows} of {importJob.total_rows} processed •
              Failures: {importJob.failed_rows}
            </div>
            {importJob.errors && importJob.errors.length > 0 && (
              <details className="mt-2">
                <summary className="cursor-pointer text-xs text-slate-500">
                  View errors ({importJob.errors.length})
                </summary>
                <pre className="mt-1 overflow-auto rounded bg-slate-100 p-2 text-xs text-slate-700">
                  {JSON.stringify(importJob.errors, null, 2)}
                </pre>
              </details>
            )}
            <div className="mt-2 text-xs text-slate-500">
              Created {new Date(importJob.created_at).toLocaleString()}
            </div>
          </article>
        ))}
        {!imports?.data?.length && !error && (
          <p className="text-sm text-slate-500">No imports queued yet.</p>
        )}
      </div>
    </div>
  );
}
