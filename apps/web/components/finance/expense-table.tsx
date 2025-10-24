'use client';

import { Expense } from '@/lib/api/expenses';
import { Button, Card } from '@church/ui';
import clsx from 'clsx';

export interface ExpenseTableProps {
  expenses: Expense[];
  isLoading?: boolean;
  isBusy?: boolean;
  onEdit?: (expense: Expense) => void;
  onSubmit?: (expense: Expense) => void;
  onApprove?: (expense: Expense) => void;
  onReimburse?: (expense: Expense) => void;
  onDelete?: (expense: Expense) => void;
}

const STATUS_BADGE_STYLES: Record<string, string> = {
  draft: 'bg-slate-200 text-slate-700',
  pending: 'bg-amber-200/60 text-amber-800',
  approved: 'bg-indigo-200/60 text-indigo-800',
  reimbursed: 'bg-emerald-200/60 text-emerald-800',
};

export function ExpenseTable({
  expenses,
  isLoading,
  isBusy,
  onEdit,
  onSubmit,
  onApprove,
  onReimburse,
  onDelete,
}: ExpenseTableProps) {
  if (isLoading) {
    return <Card padding="md">Loading expenses…</Card>;
  }

  if (expenses.length === 0) {
    return <Card padding="md">No expenses recorded yet.</Card>;
  }

  return (
    <Card padding="none">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Title
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Amount
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Status
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Incurred
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Submitted by
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 bg-white">
            {expenses.map((expense) => (
              <tr key={expense.id} className="hover:bg-slate-50/60">
                <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800">
                  {expense.title}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                  {expense.currency} {Number(expense.total_amount).toFixed(2)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm">
                  <span
                    className={clsx(
                      'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium capitalize',
                      STATUS_BADGE_STYLES[expense.status] ?? 'bg-slate-200 text-slate-700'
                    )}
                  >
                    {expense.status}
                  </span>
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                  {expense.incurred_at ? new Date(expense.incurred_at).toLocaleDateString() : '—'}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                  {expense.submitter?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-sm">
                  <div className="flex flex-wrap items-center gap-2">
                    {onEdit && expense.status === 'draft' ? (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => onEdit(expense)}
                        disabled={isBusy}
                      >
                        Edit
                      </Button>
                    ) : null}

                    {onSubmit && expense.status === 'draft' ? (
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => onSubmit(expense)}
                        disabled={isBusy}
                      >
                        Submit
                      </Button>
                    ) : null}

                    {onApprove && expense.status === 'pending' ? (
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => onApprove(expense)}
                        disabled={isBusy}
                      >
                        Approve
                      </Button>
                    ) : null}

                    {onReimburse && expense.status === 'approved' ? (
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => onReimburse(expense)}
                        disabled={isBusy}
                      >
                        Reimburse
                      </Button>
                    ) : null}

                    {onDelete && ['draft', 'pending'].includes(expense.status) ? (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="text-rose-600"
                        onClick={() => onDelete(expense)}
                        disabled={isBusy}
                      >
                        Delete
                      </Button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}
