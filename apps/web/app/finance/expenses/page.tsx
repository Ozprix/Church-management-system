'use client';

import { useMemo, useState } from 'react';
import { ExpenseForm, ExpenseFormValues } from '@/components/finance/expense-form';
import { ExpenseTable } from '@/components/finance/expense-table';
import {
  useApproveExpense,
  useCreateExpense,
  useDeleteExpense,
  useExpenses,
  useReimburseExpense,
  useSubmitExpense,
  useUpdateExpense,
} from '@/hooks/use-expenses';
import { Expense, ExpenseStatus } from '@/lib/api/expenses';
import { Button, Card } from '@church/ui';

const STATUS_FILTERS: Array<{ value: ExpenseStatus | 'all'; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending', label: 'Pending approval' },
  { value: 'approved', label: 'Approved' },
  { value: 'reimbursed', label: 'Reimbursed' },
];

function mapExpenseToFormValues(expense: Expense): ExpenseFormValues {
  return {
    title: expense.title,
    description: expense.description ?? '',
    currency: expense.currency,
    incurred_at: expense.incurred_at ?? '',
    line_items: (expense.line_items ?? []).map((item) => ({
      description: item.description,
      amount: String(item.amount ?? ''),
      category: item.category ?? undefined,
    })),
  };
}

export default function FinanceExpensesPage() {
  const [statusFilter, setStatusFilter] = useState<ExpenseStatus | undefined>(undefined);
  const [showForm, setShowForm] = useState(false);
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null);

  const { data, isLoading } = useExpenses({ status: statusFilter });
  const expenses = data?.data ?? [];

  const createExpenseMutation = useCreateExpense();
  const updateExpenseMutation = useUpdateExpense();
  const deleteExpenseMutation = useDeleteExpense();
  const submitExpenseMutation = useSubmitExpense();
  const approveExpenseMutation = useApproveExpense();
  const reimburseExpenseMutation = useReimburseExpense();

  const isBusy = useMemo(
    () =>
      createExpenseMutation.isPending ||
      updateExpenseMutation.isPending ||
      deleteExpenseMutation.isPending ||
      submitExpenseMutation.isPending ||
      approveExpenseMutation.isPending ||
      reimburseExpenseMutation.isPending,
    [
      createExpenseMutation.isPending,
      updateExpenseMutation.isPending,
      deleteExpenseMutation.isPending,
      submitExpenseMutation.isPending,
      approveExpenseMutation.isPending,
      reimburseExpenseMutation.isPending,
    ]
  );

  const handleFormSubmit = async (formValues: ExpenseFormValues) => {
    const normalizedItems = formValues.line_items
      .filter((item) => item.description.trim() !== '' && item.amount !== '')
      .map((item) => ({
        description: item.description,
        amount: Number(item.amount),
        category: item.category,
      }));

    const payload = {
      title: formValues.title,
      description: formValues.description,
      currency: formValues.currency,
      incurred_at: formValues.incurred_at,
      line_items: normalizedItems,
    };

    if (editingExpense) {
      await updateExpenseMutation.mutateAsync({ id: editingExpense.id, payload });
      setEditingExpense(null);
    } else {
      await createExpenseMutation.mutateAsync(payload);
    }

    setShowForm(false);
  };

  const handleEditExpense = (expense: Expense) => {
    setEditingExpense(expense);
    setShowForm(true);
  };

  const handleDeleteExpense = (expense: Expense) => {
    deleteExpenseMutation.mutate(expense.id);
  };

  const handleSubmitExpense = (expense: Expense) => {
    submitExpenseMutation.mutate(expense.id);
  };

  const handleApproveExpense = (expense: Expense) => {
    approveExpenseMutation.mutate(expense.id);
  };

  const handleReimburseExpense = (expense: Expense) => {
    reimburseExpenseMutation.mutate(expense.id);
  };

  const activeFilter = statusFilter ?? 'all';

  const filteredExpenses = useMemo(() => {
    if (!statusFilter) {
      return expenses;
    }

    return expenses.filter((expense) => expense.status === statusFilter);
  }, [expenses, statusFilter]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Expense Workflow</h2>
          <p className="text-sm text-slate-500">
            Log ministry expenses, submit them for approval, and track reimbursements.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button
            onClick={() => {
              setShowForm((prev) => !prev);
              setEditingExpense(null);
            }}
            disabled={isBusy}
          >
            {showForm ? 'Close form' : 'New expense'}
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {STATUS_FILTERS.map((filter) => (
          <button
            key={filter.value}
            type="button"
            onClick={() => setStatusFilter(filter.value === 'all' ? undefined : (filter.value as ExpenseStatus))}
            className={
              filter.value === activeFilter
                ? 'rounded-full bg-indigo-600 px-3 py-1 text-xs font-medium text-white'
                : 'rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-300'
            }
          >
            {filter.label}
          </button>
        ))}
      </div>

      {showForm ? (
        <Card title={editingExpense ? 'Edit expense' : 'New expense'} padding="md">
          <ExpenseForm
            defaultValues={editingExpense ? mapExpenseToFormValues(editingExpense) : undefined}
            submitting={isBusy}
            onSubmit={handleFormSubmit}
            onCancel={() => {
              setShowForm(false);
              setEditingExpense(null);
            }}
          />
        </Card>
      ) : null}

      <ExpenseTable
        expenses={filteredExpenses}
        isLoading={isLoading}
        isBusy={isBusy}
        onEdit={handleEditExpense}
        onSubmit={handleSubmitExpense}
        onApprove={handleApproveExpense}
        onReimburse={handleReimburseExpense}
        onDelete={handleDeleteExpense}
      />
    </div>
  );
}
