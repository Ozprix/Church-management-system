'use client';

import { FormEvent, useState } from 'react';
import { MemberSummary } from '@/lib/api/members';
import { Button, Input, Select } from '@church/ui';

const PAYMENT_TYPES = [
  { value: 'card', label: 'Card' },
  { value: 'bank', label: 'Bank Account' },
  { value: 'mobile_money', label: 'Mobile Money' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' },
];

export interface PaymentMethodFormValues {
  member_id?: number | null;
  type?: string;
  brand?: string;
  last_four?: string;
  provider?: string;
  provider_reference?: string;
  is_default?: boolean;
}

export interface PaymentMethodFormProps {
  members: MemberSummary[];
  defaultValues?: PaymentMethodFormValues;
  onSubmit: (values: PaymentMethodFormValues) => Promise<void> | void;
  submitting?: boolean;
  onCancel?: () => void;
}

export function PaymentMethodForm({ members, defaultValues, onSubmit, submitting, onCancel }: PaymentMethodFormProps) {
  const [values, setValues] = useState<PaymentMethodFormValues>({
    member_id: defaultValues?.member_id ?? undefined,
    type: defaultValues?.type ?? 'card',
    brand: defaultValues?.brand ?? '',
    last_four: defaultValues?.last_four ?? '',
    provider: defaultValues?.provider ?? '',
    provider_reference: defaultValues?.provider_reference ?? '',
    is_default: defaultValues?.is_default ?? false,
  });

  const handleChange = (field: keyof PaymentMethodFormValues, value: unknown) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    await onSubmit(values);
  };

  return (
    <form className="space-y-4" onSubmit={handleSubmit}>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Member</span>
          <Select
            value={values.member_id ? String(values.member_id) : ''}
            onChange={(event) => handleChange('member_id', event.target.value ? Number(event.target.value) : null)}
          >
            <option value="">Guest / Unassigned</option>
            {members.map((member) => (
              <option key={member.id} value={member.id}>
                {member.first_name} {member.last_name}
              </option>
            ))}
          </Select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Method Type</span>
          <Select value={values.type} onChange={(event) => handleChange('type', event.target.value)} required>
            {PAYMENT_TYPES.map((type) => (
              <option key={type.value} value={type.value}>
                {type.label}
              </option>
            ))}
          </Select>
        </label>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Brand</span>
          <Input value={values.brand ?? ''} onChange={(event) => handleChange('brand', event.target.value)} />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Last four</span>
          <Input
            value={values.last_four ?? ''}
            onChange={(event) => handleChange('last_four', event.target.value)}
            maxLength={4}
          />
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            className="h-4 w-4 rounded border-slate-300"
            checked={values.is_default ?? false}
            onChange={(event) => handleChange('is_default', event.target.checked)}
          />
          <span className="text-slate-600">Set as default</span>
        </label>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Provider</span>
          <Input value={values.provider ?? ''} onChange={(event) => handleChange('provider', event.target.value)} />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-slate-600">Provider Reference</span>
          <Input
            value={values.provider_reference ?? ''}
            onChange={(event) => handleChange('provider_reference', event.target.value)}
          />
        </label>
      </div>

      <div className="flex items-center justify-end gap-3">
        {onCancel ? (
          <Button type="button" variant="ghost" onClick={onCancel} disabled={submitting}>
            Cancel
          </Button>
        ) : null}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Saving…' : 'Save Method'}
        </Button>
      </div>
    </form>
  );
}
