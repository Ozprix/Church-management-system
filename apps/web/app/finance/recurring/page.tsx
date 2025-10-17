'use client';

import { FormEvent, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import {
  RecurringDonationSchedule,
  RecurringDonationAttempt,
  RecurringStatus,
} from '@/lib/api/recurring-donations';
import {
  useRecurringDonationSchedules,
  useRecurringDonationAttempts,
  useRecurringDonationMutation,
} from '@/hooks/use-recurring-donations';
import { usePaymentMethods } from '@/hooks/use-payment-methods';
import { useMembers } from '@/hooks/use-members';
import { useTenantId } from '@/lib/tenant';
import { getApiBaseUrl } from '@/lib/api/env';
import {
  Badge,
  Button,
  Card,
  Input,
  Label,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
  useToast,
} from '@church/ui';
import { ApiError } from '@/lib/api/http';

const FREQUENCY_OPTIONS = [
  { value: 'weekly', label: 'Weekly' },
  { value: 'biweekly', label: 'Biweekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'annually', label: 'Annually' },
];

function statusBadgeVariant(status: RecurringStatus) {
  switch (status) {
    case 'active':
      return 'success';
    case 'paused':
      return 'warning';
    case 'cancelled':
    default:
      return 'default';
  }
}

export default function RecurringFinancePage() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  const { data: schedulesResponse } = useRecurringDonationSchedules();
  const schedules = schedulesResponse?.data ?? [];

  const { data: membersData } = useMembers({ per_page: 50 });
  const memberOptions = useMemo(
    () =>
      (membersData?.data ?? []).map((member) => ({
        value: member.id,
        label: `${member.first_name} ${member.last_name}`.trim(),
      })),
    [membersData?.data]
  );

  const { data: paymentMethodsResponse } = usePaymentMethods();
  const paymentMethods = paymentMethodsResponse?.data ?? [];

  const { createMutation, updateMutation, deleteMutation } = useRecurringDonationMutation();

  const [formState, setFormState] = useState({
    member_id: '',
    payment_method_id: '',
    frequency: 'monthly' as const,
    amount: '50',
    currency: 'USD',
    starts_on: new Date().toISOString().slice(0, 10),
    ends_on: '',
  });

  const [selectedSchedule, setSelectedSchedule] = useState<RecurringDonationSchedule | null>(null);
  const { data: attemptsResponse } = useRecurringDonationAttempts(selectedSchedule?.id);
  const attempts = attemptsResponse?.data ?? [];

  const handleCreateSchedule = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!tenantId) {
      pushToast({ title: 'Error', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    createMutation.mutate(
      {
        member_id: formState.member_id ? Number(formState.member_id) : undefined,
        payment_method_id: formState.payment_method_id ? Number(formState.payment_method_id) : undefined,
        frequency: formState.frequency,
        amount: Number(formState.amount),
        currency: formState.currency,
        starts_on: formState.starts_on,
        ends_on: formState.ends_on || undefined,
      },
      {
        onSuccess: () => {
          setFormState((prev) => ({
            ...prev,
            member_id: '',
            payment_method_id: '',
            amount: '50',
            ends_on: '',
          }));
          queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
          pushToast({ title: 'Recurring schedule created', variant: 'success' });
        },
        onError: (error: unknown) => {
          const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to create schedule';
          pushToast({ title: 'Error', description: message, variant: 'error' });
        },
      }
    );
  };

  const handleStatusChange = (schedule: RecurringDonationSchedule, status: RecurringStatus) => {
    updateMutation.mutate(
      { id: schedule.id, payload: { status } },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
          pushToast({ title: `Schedule ${status === 'cancelled' ? 'cancelled' : 'updated'}`, variant: 'success' });
        },
        onError: (error: unknown) => {
          const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to update schedule';
          pushToast({ title: 'Error', description: message, variant: 'error' });
        },
      }
    );
  };

  const handleDeleteSchedule = (schedule: RecurringDonationSchedule) => {
    deleteMutation.mutate(schedule.id, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
        pushToast({ title: 'Schedule deleted', variant: 'success' });
        if (selectedSchedule?.id === schedule.id) {
          setSelectedSchedule(null);
        }
      },
      onError: (error: unknown) => {
        const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to delete schedule';
        pushToast({ title: 'Error', description: message, variant: 'error' });
      },
    });
  };

  const handleDownload = async (path: string, filename: string) => {
    if (!tenantId) {
      pushToast({ title: 'Error', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    const baseUrl = getApiBaseUrl();
    try {
      const response = await fetch(`${baseUrl}${path}`, {
        headers: {
          'X-Tenant-ID': tenantId,
        },
      });

      if (!response.ok) {
        throw new Error(`Export failed (${response.status})`);
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to download export';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  return (
    <div className="space-y-8">
      <section>
        <Card className="space-y-4">
          <div className="flex flex-col gap-2">
            <h2 className="text-xl font-semibold text-slate-900">Recurring Giving Schedules</h2>
            <p className="text-sm text-slate-500">Create and manage automated donation schedules for members.</p>
          </div>

          <form className="grid gap-4 md:grid-cols-6" onSubmit={handleCreateSchedule}>
            <div className="md:col-span-2">
              <Label htmlFor="member-select">Member</Label>
              <Select
                id="member-select"
                value={formState.member_id}
                onChange={(event) => setFormState((prev) => ({ ...prev, member_id: event.target.value }))}
              >
                <option value="">Select member…</option>
                {memberOptions.map((member) => (
                  <option key={member.value} value={member.value}>
                    {member.label}
                  </option>
                ))}
              </Select>
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="payment-method-select">Payment Method</Label>
              <Select
                id="payment-method-select"
                value={formState.payment_method_id}
                onChange={(event) => setFormState((prev) => ({ ...prev, payment_method_id: event.target.value }))}
              >
                <option value="">Select method…</option>
                {paymentMethods.map((method) => (
                  <option key={method.id} value={method.id}>
                    {method.brand ?? method.type} {method.last_four ? `•••• ${method.last_four}` : ''}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label htmlFor="frequency-select" required>
                Frequency
              </Label>
              <Select
                id="frequency-select"
                value={formState.frequency}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    frequency: event.target.value as typeof prev.frequency,
                  }))
                }
                required
              >
                {FREQUENCY_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label htmlFor="amount" required>
                Amount
              </Label>
              <Input
                id="amount"
                type="number"
                min="1"
                step="0.01"
                value={formState.amount}
                onChange={(event) => setFormState((prev) => ({ ...prev, amount: event.target.value }))}
                required
              />
            </div>
            <div>
              <Label htmlFor="currency" required>
                Currency
              </Label>
              <Input
                id="currency"
                value={formState.currency}
                onChange={(event) => setFormState((prev) => ({ ...prev, currency: event.target.value.toUpperCase() }))}
                required
              />
            </div>
            <div>
              <Label htmlFor="start-date" required>
                Starts
              </Label>
              <Input
                id="start-date"
                type="date"
                value={formState.starts_on}
                onChange={(event) => setFormState((prev) => ({ ...prev, starts_on: event.target.value }))}
                required
              />
            </div>
            <div>
              <Label htmlFor="end-date">Ends</Label>
              <Input
                id="end-date"
                type="date"
                value={formState.ends_on}
                onChange={(event) => setFormState((prev) => ({ ...prev, ends_on: event.target.value }))}
              />
            </div>
            <div className="md:col-span-6 flex justify-end">
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Creating…' : 'Create schedule'}
              </Button>
            </div>
          </form>

          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Member</TableHeaderCell>
                  <TableHeaderCell>Amount</TableHeaderCell>
                  <TableHeaderCell>Frequency</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Next run</TableHeaderCell>
                  <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {schedules.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="py-4 text-center text-sm text-slate-500">
                      No recurring schedules configured yet.
                    </TableCell>
                  </TableRow>
                ) : (
                  schedules.map((schedule) => (
                    <TableRow key={schedule.id}>
                      <TableCell>
                        {schedule.member ? (
                          <Link
                            href={`/members/${schedule.member.id}`}
                            className="text-sm font-medium text-emerald-600 hover:text-emerald-700"
                          >
                            {`${schedule.member.first_name ?? ''} ${schedule.member.last_name ?? ''}`.trim() || schedule.member.id}
                          </Link>
                        ) : (
                          <span className="text-sm text-slate-500">N/A</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {schedule.currency} {Number(schedule.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </TableCell>
                      <TableCell className="capitalize">{schedule.frequency}</TableCell>
                      <TableCell>
                        <Badge variant={statusBadgeVariant(schedule.status)}>{schedule.status}</Badge>
                      </TableCell>
                      <TableCell>
                        {schedule.next_run_at ? new Date(schedule.next_run_at).toLocaleString() : '—'}
                      </TableCell>
                      <TableCell className="flex items-center justify-end gap-2">
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() => setSelectedSchedule(schedule)}
                        >
                          View attempts
                        </Button>
                        {schedule.status !== 'cancelled' ? (
                          <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                              handleStatusChange(
                                schedule,
                                schedule.status === 'active' ? 'paused' : 'active'
                              )
                            }
                          >
                            {schedule.status === 'active' ? 'Pause' : 'Resume'}
                          </Button>
                        ) : null}
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() => handleStatusChange(schedule, 'cancelled')}
                        >
                          Cancel
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() => handleDeleteSchedule(schedule)}
                        >
                          Delete
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </section>

      <section>
        <Card className="space-y-4">
          <div className="flex flex-col gap-2">
            <h2 className="text-xl font-semibold text-slate-900">Exports & Statements</h2>
            <p className="text-sm text-slate-500">Download CSV exports for donations, pledges, or donor statements.</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => handleDownload('/v1/finance/reports/donations/export', 'donations.csv')}
            >
              Download Donations CSV
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => handleDownload('/v1/finance/reports/pledges/export', 'pledges.csv')}
            >
              Download Pledges CSV
            </Button>
            {selectedSchedule?.member ? (
              <Button
                type="button"
                variant="secondary"
                onClick={() =>
                  handleDownload(
                    `/v1/finance/reports/donor-statement/${selectedSchedule.member?.id}`,
                    `donor-statement-${selectedSchedule.member?.id}.csv`
                  )
                }
              >
                Donor Statement (selected member)
              </Button>
            ) : null}
          </div>
        </Card>
      </section>

      {selectedSchedule ? (
        <section>
          <Card className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">Attempt History</h2>
                <p className="text-sm text-slate-500">
                  Schedule #{selectedSchedule.id} — {selectedSchedule.frequency} {selectedSchedule.currency}{' '}
                  {Number(selectedSchedule.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </p>
              </div>
              <Button type="button" variant="ghost" onClick={() => setSelectedSchedule(null)}>
                Close
              </Button>
            </div>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableHeaderCell>ID</TableHeaderCell>
                    <TableHeaderCell>Status</TableHeaderCell>
                    <TableHeaderCell>Processed</TableHeaderCell>
                    <TableHeaderCell>Amount</TableHeaderCell>
                    <TableHeaderCell>Reference</TableHeaderCell>
                    <TableHeaderCell>Failure reason</TableHeaderCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {attempts.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="py-4 text-center text-sm text-slate-500">
                        No attempts recorded yet.
                      </TableCell>
                    </TableRow>
                  ) : (
                    attempts.map((attempt: RecurringDonationAttempt) => (
                      <TableRow key={attempt.id}>
                        <TableCell>{attempt.id}</TableCell>
                        <TableCell className="capitalize">{attempt.status}</TableCell>
                        <TableCell>
                          {attempt.processed_at ? new Date(attempt.processed_at).toLocaleString() : '—'}
                        </TableCell>
                        <TableCell>
                          {attempt.currency}{' '}
                          {Number(attempt.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </TableCell>
                        <TableCell>{attempt.provider_reference ?? '—'}</TableCell>
                        <TableCell>{attempt.failure_reason ?? '—'}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </TableContainer>
          </Card>
        </section>
      ) : null}
    </div>
  );
}
