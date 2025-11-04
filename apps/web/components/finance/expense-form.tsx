'use client';

import { FormEvent, useState } from 'react';
import { Button, Input } from '@church/ui';

export interface ExpenseLineItemInput {
  description: string;
  amount: string;
  category?: string;
}

export interface ExpenseFormValues {
  title: string;
  description?: string;
  currency: string;
  incurred_at?: string;
  line_items: ExpenseLineItemInput[];
}

export interface ExpenseFormProps {
  defaultValues?: Partial<ExpenseFormValues>;
  submitting?: boolean;
  onSubmit: (values: ExpenseFormValues) => Promise<void> | void;
  onCancel?: () => void;
}

const defaultLineItem = (): ExpenseLineItemInput => ({
  description: '',
  amount: '',
  category: '',
});

export function ExpenseForm({ defaultValues, submitting, onSubmit, onCancel }: ExpenseFormProps) {
  const [values, setValues] = useState<ExpenseFormValues>({
    title: defaultValues?.title ?? '',
    description: defaultValues?.description ?? '',
    currency: defaultValues?.currency ?? 'USD',
    incurred_at: defaultValues?.incurred_at ?? '',
    line_items:
      defaultValues?.line_items?.map((item) => ({
        description: item.description,
        amount: String(item.amount ?? ''),
        category: item.category ?? '',
      })) ?? [defaultLineItem()],
  });

  const handleChange = (field: keyof ExpenseFormValues, value: unknown) => {
    setValues((prev) => ({ ...prev, [field]: value as never }));
  };

  const handleLineItemChange = (index: number, field: keyof ExpenseLineItemInput, value: string) => {
    setValues((prev) => {
      const next = [...prev.line_items];
      next[index] = { ...next[index], [field]: value };
      return { ...prev, line_items: next };
    });
  };

  const handleAddLineItem = () => {
    setValues((prev) => ({ ...prev, line_items: [...prev.line_items, defaultLineItem()] }));
  };

  const handleRemoveLineItem = (index: number) => {
    setValues((prev) => ({
      ...prev,
      line_items: prev.line_items.length > 1 ? prev.line_items.filter((_, idx) => idx !== index) : prev.line_items,
    }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedItems = values.line_items.map((item) => ({
      description: item.description,
      amount: item.amount === '' ? '' : String(item.amount),
      category: item.category || undefined,
    }));

    await onSubmit({
      ...values,
      line_items: normalizedItems,
    });
  };

  return (
    <form className="space-y-4" onSubmit={handleSubmit}>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Title</span>
          <Input
            required
            value={values.title}
            onChange={(event) => handleChange('title', event.target.value)}
            placeholder="Team outing reimbursement"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Currency</span>
          <Input
            value={values.currency}
            onChange={(event) => handleChange('currency', event.target.value.toUpperCase())}
            maxLength={3}
            placeholder="USD"
          />
        </label>
      </div>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-slate-600">Description</span>
        <textarea
          className="min-h-[96px] rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
          value={values.description ?? ''}
          onChange={(event) => handleChange('description', event.target.value)}
          placeholder="Add context for the approver…"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm md:w-48">
        <span className="text-slate-600">Incurred date</span>
        <Input
          type="date"
          value={values.incurred_at ?? ''}
          onChange={(event) => handleChange('incurred_at', event.target.value)}
        />
      </label>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-slate-700">Line Items</span>
          <Button type="button" variant="ghost" onClick={handleAddLineItem}>
            Add item
          </Button>
        </div>

        <div className="space-y-3">
          {values.line_items.map((item, index) => (
            <div
              key={`expense-line-item-${index}`}
              className="grid gap-3 rounded-md border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-5"
            >
              <label className="flex flex-col gap-1 text-sm md:col-span-2">
                <span className="text-slate-600">Description</span>
                <Input
                  required
                  value={item.description}
                  onChange={(event) => handleLineItemChange(index, 'description', event.target.value)}
                  placeholder="Receipt description"
                />
              </label>
              <label className="flex flex-col gap-1 text-sm md:col-span-1">
                <span className="text-slate-600">Amount</span>
                <Input
                  required
                  type="number"
                  min="0"
                  step="0.01"
                  value={item.amount}
                  onChange={(event) => handleLineItemChange(index, 'amount', event.target.value)}
                  placeholder="0.00"
                />
              </label>
              <label className="flex flex-col gap-1 text-sm md:col-span-1">
                <span className="text-slate-600">Category</span>
                <Input
                  value={item.category ?? ''}
                  onChange={(event) => handleLineItemChange(index, 'category', event.target.value)}
                  placeholder="Optional tag"
                />
              </label>
              <div className="flex items-end justify-end md:col-span-1">
                <Button
                  type="button"
                  variant="ghost"
                  className="text-sm text-rose-600"
                  onClick={() => handleRemoveLineItem(index)}
                  disabled={values.line_items.length === 1}
                >
                  Remove
                </Button>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="flex items-center justify-end gap-3">
        {onCancel ? (
          <Button type="button" variant="ghost" onClick={onCancel} disabled={submitting}>
            Cancel
          </Button>
        ) : null}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Saving…' : 'Save expense'}
        </Button>
      </div>
    </form>
  );
}
