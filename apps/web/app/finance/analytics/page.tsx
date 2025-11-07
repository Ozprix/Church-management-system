'use client';

import { FormEvent, useState } from 'react';
import Link from 'next/link';
import {
  Badge,
  Button,
  Card,
  Input,
  Label,
  Select,
  StatCard,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
  useToast,
} from '@church/ui';
import { useFinanceAnalytics } from '@/hooks/use-finance-analytics';
import { useTenantId } from '@/lib/tenant';
import { FinanceAnalyticsFilters, buildFinanceAnalyticsExportUrl } from '@/lib/api/finance';
import { getApiBaseUrl } from '@/lib/api/env';
import { downloadFromApi } from '@/lib/download';

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

function formatCurrency(amount: number, currency = 'USD') {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount);
}

export default function FinanceAnalyticsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [filters, setFilters] = useState<FinanceAnalyticsFilters>({});
  const { data, isLoading } = useFinanceAnalytics(filters);

  const availableStatuses = data?.filters?.statuses ?? [];
  const availableFunds = data?.filters?.funds ?? [];
  const dateRange = data?.filters?.date_range ?? {};

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);

    setFilters({
      status: (formData.get('status') as string) || undefined,
      fund_id: formData.get('fund_id') ? Number(formData.get('fund_id')) : undefined,
      date_from: (formData.get('date_from') as string) || undefined,
      date_to: (formData.get('date_to') as string) || undefined,
    });
  };

  const handleReset = () => {
    setFilters({});
  };

  const handleExport = async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const path = buildFinanceAnalyticsExportUrl(filters);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, 'finance-analytics.csv');
      pushToast({ title: 'Export ready', description: 'Analytics CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  };

  if (isLoading) {
    return <p className="text-slate-500">Loading finance analytics…</p>;
  }

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Finance analytics</h2>
          <p className="text-sm text-slate-500">Dig into giving trends, fund performance, and donor engagement.</p>
        </div>
        <Link href="/finance" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to finance overview
        </Link>
      </div>

      <form
        key={JSON.stringify(filters)}
        className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4"
        onSubmit={handleSubmit}
      >
        <div>
          <Label htmlFor="status">Status</Label>
          <Select id="status" name="status" defaultValue={filters.status ?? ''}>
            <option value="">All statuses</option>
            {availableStatuses.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="fund_id">Fund</Label>
          <Select id="fund_id" name="fund_id" defaultValue={filters.fund_id ? String(filters.fund_id) : ''}>
            <option value="">All funds</option>
            {availableFunds.map((fund) => (
              <option key={fund.id} value={fund.id}>
                {fund.name}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="date_from">Date from</Label>
          <Input
            id="date_from"
            name="date_from"
            type="date"
            defaultValue={filters.date_from ?? ''}
            min={dateRange.earliest ?? undefined}
            max={dateRange.latest ?? undefined}
          />
        </div>
        <div>
          <Label htmlFor="date_to">Date to</Label>
          <Input
            id="date_to"
            name="date_to"
            type="date"
            defaultValue={filters.date_to ?? ''}
            min={dateRange.earliest ?? undefined}
            max={dateRange.latest ?? undefined}
          />
        </div>
        <div className="md:col-span-4 flex items-center justify-end gap-2">
          <Button type="submit" variant="secondary">
            Apply filters
          </Button>
          <Button type="button" variant="ghost" onClick={handleReset}>
            Reset
          </Button>
          <Button type="button" onClick={handleExport}>
            Export CSV
          </Button>
        </div>
      </form>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Succeeded donations"
          value={formatCurrency(data?.totals.donations_amount ?? 0)}
          helperText="All-time successful donations"
          tone="success"
        />
        <StatCard
          title="This month"
          value={formatCurrency(data?.totals.donations_this_month ?? 0)}
          helperText="Received since month start"
        />
        <StatCard
          title="Average donation"
          value={formatCurrency(data?.totals.average_donation ?? 0)}
        />
        <StatCard
          title="Active pledges"
          value={data?.totals.active_pledges ?? '—'}
          helperText="Scheduled commitments in progress"
        />
      </div>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Donations by status</h3>
          <p className="text-xs text-slate-500">Volume and totals per status</p>
        </header>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Status</TableHeaderCell>
                <TableHeaderCell>Count</TableHeaderCell>
                <TableHeaderCell>Amount</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data?.by_status ?? []).length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-4 text-center text-sm text-slate-500">
                    No donation activity.
                  </TableCell>
                </TableRow>
              ) : (
                (data?.by_status ?? []).map((row) => (
                  <TableRow key={row.status}>
                    <TableCell className="capitalize">{row.status}</TableCell>
                    <TableCell>{row.count}</TableCell>
                    <TableCell>{formatCurrency(row.amount)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Top funds</h3>
          <p className="text-xs text-slate-500">Succeeded donations by fund</p>
        </header>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Fund</TableHeaderCell>
                <TableHeaderCell>Amount</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data?.by_fund ?? []).length === 0 ? (
                <TableRow>
                  <TableCell colSpan={2} className="py-4 text-center text-sm text-slate-500">
                    No fund data.
                  </TableCell>
                </TableRow>
              ) : (
                (data?.by_fund ?? []).map((fund) => (
                  <TableRow key={fund.fund_id ?? `fund-${fund.fund_name}`}
                  >
                    <TableCell>{fund.fund_name ?? 'Unassigned'}</TableCell>
                    <TableCell>{formatCurrency(fund.amount)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Monthly giving trend</h3>
          <p className="text-xs text-slate-500">Last six months of succeeded donations</p>
        </header>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Month</TableHeaderCell>
                <TableHeaderCell>Amount</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data?.donations_trend ?? []).length === 0 ? (
                <TableRow>
                  <TableCell colSpan={2} className="py-4 text-center text-sm text-slate-500">
                    No history available.
                  </TableCell>
                </TableRow>
              ) : (
                (data?.donations_trend ?? []).map((entry) => (
                  <TableRow key={entry.label}>
                    <TableCell>{entry.label}</TableCell>
                    <TableCell>{formatCurrency(entry.value)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Top donors</h3>
            <p className="text-xs text-slate-500">Leading contributors</p>
          </header>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Donor</TableHeaderCell>
                  <TableHeaderCell>Amount</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {(data?.top_donors ?? []).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={2} className="py-4 text-center text-sm text-slate-500">
                      No donor data available.
                    </TableCell>
                  </TableRow>
                ) : (
                  (data?.top_donors ?? []).map((donor) => (
                    <TableRow key={`${donor.member_id ?? donor.member_name}`}>
                      <TableCell>{donor.member_name}</TableCell>
                      <TableCell>{formatCurrency(donor.total)}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Recent donations</h3>
            <p className="text-xs text-slate-500">Last five transactions</p>
          </header>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Donor</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Amount</TableHeaderCell>
                  <TableHeaderCell>Date</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {(data?.recent_donations ?? []).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="py-4 text-center text-sm text-slate-500">
                      No donations recorded.
                    </TableCell>
                  </TableRow>
                ) : (
                  (data?.recent_donations ?? []).map((donation) => (
                    <TableRow key={donation.id}>
                      <TableCell>{donation.member_name ?? 'Anonymous'}</TableCell>
                      <TableCell>
                        <Badge
                          variant={donation.status === 'succeeded' ? 'success' : donation.status === 'refunded' ? 'warning' : 'info'}
                        >
                          {donation.status}
                        </Badge>
                      </TableCell>
                      <TableCell>{formatCurrency(donation.amount)}</TableCell>
                      <TableCell>{donation.received_at ? new Date(donation.received_at).toLocaleString() : '—'}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </div>
    </section>
  );
}
