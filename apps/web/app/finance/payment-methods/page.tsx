'use client';

import { useState } from 'react';
import { PaymentMethodForm, PaymentMethodFormValues } from '@/components/finance/payment-method-form';
import { PaymentMethodList } from '@/components/finance/payment-method-list';
import { useMembers } from '@/hooks/use-members';
import {
  useCreatePaymentMethod,
  useDeletePaymentMethod,
  usePaymentMethods,
  useUpdatePaymentMethod,
} from '@/hooks/use-payment-methods';
import { Button, Card } from '@church/ui';

export default function PaymentMethodsPage() {
  const [showForm, setShowForm] = useState(false);
  const { data: methodsResponse, isLoading } = usePaymentMethods();
  const { data: membersResponse } = useMembers({ per_page: 50 });
  const createMutation = useCreatePaymentMethod();
  const updateMutation = useUpdatePaymentMethod();
  const deleteMutation = useDeletePaymentMethod();

  const members = membersResponse?.data ?? [];
  const methods = methodsResponse?.data ?? [];

  const handleCreate = async (values: PaymentMethodFormValues) => {
    await createMutation.mutateAsync({
      ...values,
      member_id: values.member_id ?? undefined,
    });
    setShowForm(false);
  };

  const handleSetDefault = (id: number) => {
    updateMutation.mutate({ id, payload: { is_default: true } });
  };

  const handleDelete = (id: number) => {
    deleteMutation.mutate(id);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Payment Methods</h2>
          <p className="text-sm text-slate-500">
            Capture bank cards, mobile money wallets, and cash references per member.
          </p>
        </div>
        <div>
          <Button onClick={() => setShowForm((prev) => !prev)}>
            {showForm ? 'Close form' : 'Add payment method'}
          </Button>
        </div>
      </div>

      {showForm ? (
        <Card title="New payment method" padding="md">
          <PaymentMethodForm
            members={members}
            onSubmit={handleCreate}
            submitting={createMutation.isPending}
            onCancel={() => setShowForm(false)}
          />
        </Card>
      ) : null}

      <Card title="Stored methods" padding="md">
        {isLoading ? (
          <p className="text-sm text-slate-500">Loading payment methods…</p>
        ) : (
          <PaymentMethodList
            methods={methods}
            onSetDefault={handleSetDefault}
            onDelete={handleDelete}
          />
        )}
      </Card>
    </div>
  );
}
