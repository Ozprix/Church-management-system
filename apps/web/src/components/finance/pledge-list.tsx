import { format, parseISO } from "date-fns";
import type { PledgeSummary } from "@/types/member";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

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

export function PledgeList({
  pledges,
  selectedPledgeId,
  onSelect,
  isLoading,
  emptyState,
  className,
  title = "Pledges",
  description,
}: {
  pledges: PledgeSummary[];
  selectedPledgeId: number | null;
  onSelect: (pledgeId: number) => void;
  isLoading?: boolean;
  emptyState?: React.ReactNode;
  className?: string;
  title?: React.ReactNode;
  description?: React.ReactNode;
}) {
  return (
    <Card className={className}>
      <CardHeader>
        <CardTitle className="text-lg">{title}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent className="overflow-x-auto p-0">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-600">
            <tr>
              <th className="px-4 py-3">Member</th>
              <th className="px-4 py-3">Fund</th>
              <th className="px-4 py-3">Amount</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Reminder</th>
              <th className="px-4 py-3">Next reminder</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {pledges.map((pledge) => {
              const isSelected = pledge.id === selectedPledgeId;
              const memberName = pledge.member
                ? `${pledge.member.first_name} ${pledge.member.last_name}`
                : "—";
              const fundName = pledge.fund?.name ?? "—";
              return (
                <tr
                  key={pledge.id}
                  onClick={() => onSelect(pledge.id)}
                  className={`cursor-pointer ${isSelected ? "bg-slate-100" : "hover:bg-slate-50"}`}
                >
                  <td className="px-4 py-3 text-slate-900">{memberName}</td>
                  <td className="px-4 py-3 text-slate-700">{fundName}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatCurrency(pledge.amount, pledge.currency)}
                  </td>
                  <td className="px-4 py-3 capitalize text-slate-700">{pledge.status}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {pledge.reminder_enabled
                      ? `Enabled • ${pledge.reminder_cadence ?? "—"}`
                      : "Disabled"}
                  </td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatDateTime(pledge.next_reminder_at)}
                  </td>
                </tr>
              );
            })}

            {!isLoading && pledges.length === 0 && (
              <tr>
                <td className="px-4 py-6 text-center text-slate-500" colSpan={6}>
                  {emptyState ?? "No pledges found for this tenant."}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </CardContent>
      {isLoading && (
        <div className="border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
          Loading pledges…
        </div>
      )}
    </Card>
  );
}
