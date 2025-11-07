import { format, parseISO } from "date-fns";
import type { NotificationSummary } from "@/types/member";

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

export function ReminderHistory({
  entries,
  isLoading,
  emptyState,
  className,
}: {
  entries: NotificationSummary[];
  isLoading?: boolean;
  emptyState?: React.ReactNode;
  className?: string;
}) {
  const containerClass = className ? `space-y-4 ${className}` : "space-y-4";

  return (
    <div className={containerClass}>
      {isLoading && <p className="text-sm text-slate-500">Loading reminders…</p>}

      {!isLoading && entries.length === 0 && (
        <p className="text-sm text-slate-500">
          {emptyState ?? "No reminders have been sent yet."}
        </p>
      )}

      {entries.map((entry) => (
        <article key={entry.id} className="rounded border border-slate-200 bg-white p-3 text-sm">
          <div className="flex items-baseline justify-between gap-3">
            <div>
              <p className="font-medium text-slate-800">
                {entry.channel.toUpperCase()} • {entry.status}
              </p>
              <p className="text-xs text-slate-500">
                {formatDateTime(entry.sent_at ?? entry.created_at)}
              </p>
            </div>
            <p className="text-xs text-slate-500">
              {entry.recipient ? entry.recipient : "No recipient"}
            </p>
          </div>

          {entry.subject && (
            <p className="mt-2 font-medium text-slate-800">{entry.subject}</p>
          )}

          {entry.body && (
            <p className="mt-1 whitespace-pre-wrap text-slate-600">{entry.body}</p>
          )}

          {entry.error_message && (
            <p className="mt-2 text-xs text-red-600">Error: {entry.error_message}</p>
          )}
        </article>
      ))}
    </div>
  );
}
