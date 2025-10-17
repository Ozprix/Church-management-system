'use client';

import { FormEvent, useState } from 'react';
import Link from 'next/link';
import { Card, StatCard, Table, TableBody, TableCell, TableContainer, TableHead, TableHeaderCell, TableRow, Button, Label, Input, Select, useToast } from '@church/ui';
import { downloadFromApi } from '@/lib/download';
import { getApiBaseUrl } from '@/lib/api/env';
import { buildMemberAnalyticsExportUrl, MemberAnalyticsFilters } from '@/lib/api/members';
import { useMemberAnalytics } from '@/hooks/use-member-analytics';
import { useTenantId } from '@/lib/tenant';

export default function MemberAnalyticsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [filters, setFilters] = useState<MemberAnalyticsFilters>({});
  const { data, isLoading } = useMemberAnalytics(filters);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const status = (formData.get('status') as string) || undefined;
    const stage = (formData.get('stage') as string) || undefined;
    const joinedFrom = (formData.get('joined_from') as string) || undefined;
    const joinedTo = (formData.get('joined_to') as string) || undefined;
    const withFamilyRaw = formData.get('with_family') as string | null;
    const withFamily = withFamilyRaw === '' ? null : withFamilyRaw === 'true';

    setFilters({
      status,
      stage,
      joined_from: joinedFrom,
      joined_to: joinedTo,
      with_family: withFamily,
    });
  };

  const handleExport = async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const path = buildMemberAnalyticsExportUrl(filters);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      const token = process.env.NEXT_PUBLIC_API_TOKEN;
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }
      await downloadFromApi(url, { headers }, 'member-analytics.csv');
      pushToast({ title: 'Export ready', description: 'Analytics CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  };

  if (isLoading) {
    return <p className="text-slate-500">Loading analytics…</p>;
  }

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Member analytics</h2>
          <p className="text-sm text-slate-500">Track membership trends, engagement, and follow-up priorities.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <form className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5" onSubmit={handleSubmit}>
        <div>
          <Label htmlFor="status">Status</Label>
          <Input id="status" name="status" placeholder="e.g. active" defaultValue={filters.status ?? ''} />
        </div>
        <div>
          <Label htmlFor="stage">Stage</Label>
          <Input id="stage" name="stage" placeholder="e.g. newcomer" defaultValue={filters.stage ?? ''} />
        </div>
        <div>
          <Label htmlFor="joined_from">Joined from</Label>
          <Input id="joined_from" name="joined_from" type="date" defaultValue={filters.joined_from ?? ''} />
        </div>
        <div>
          <Label htmlFor="joined_to">Joined to</Label>
          <Input id="joined_to" name="joined_to" type="date" defaultValue={filters.joined_to ?? ''} />
        </div>
        <div>
          <Label htmlFor="with_family">Family assignment</Label>
          <Select id="with_family" name="with_family" defaultValue={filters.with_family === null || filters.with_family === undefined ? '' : String(filters.with_family)}>
            <option value="">All members</option>
            <option value="true">Has family</option>
            <option value="false">No family</option>
          </Select>
        </div>
        <div className="md:col-span-5 flex items-center justify-end gap-2">
          <Button type="submit" variant="secondary">
            Apply filters
          </Button>
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              setFilters({});
            }}
          >
            Reset
          </Button>
          <Button type="button" onClick={handleExport}>
            Export CSV
          </Button>
        </div>
      </form>

      <div className="grid gap-4 md:grid-cols-3">
        <StatCard title="Total members" value={data?.totals.members ?? '—'} />
        <StatCard
          title="Without family"
          value={data?.totals.members_without_family ?? '—'}
          helperText="Members not assigned to a household"
          tone={(data?.totals.members_without_family ?? 0) > 0 ? 'warning' : 'default'}
        />
        <StatCard
          title="Profiles to refresh"
          value={data?.totals.stale_profiles ?? '—'}
          helperText="Haven’t been updated in 6+ months"
          tone={(data?.totals.stale_profiles ?? 0) > 0 ? 'warning' : 'default'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Status distribution</h3>
            <p className="text-xs text-slate-500">By membership status</p>
          </header>
          <DistributionList
            items={data?.by_status ?? []}
            labelKey="status"
            emptyLabel="No status data"
          />
        </Card>

        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Stage breakdown</h3>
            <p className="text-xs text-slate-500">Top membership stages</p>
          </header>
          <DistributionList
            items={data?.by_stage ?? []}
            labelKey="stage"
            emptyLabel="No stage data"
          />
        </Card>
      </div>

      <Card className="space-y-4">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">New members trend</h3>
          <p className="text-xs text-slate-500">Last 6 months</p>
        </header>
        {data?.new_members_trend?.length ? (
          <TrendChart data={data.new_members_trend} />
        ) : (
          <p className="text-sm text-slate-500">No recent activity.</p>
        )}
      </Card>

      <Card className="space-y-4">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Recently added members</h3>
          <p className="text-xs text-slate-500">Latest five profiles</p>
        </header>
        {data?.recent_members?.length ? (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Name</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Stage</TableHeaderCell>
                  <TableHeaderCell>Joined</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.recent_members.map((member) => (
                  <TableRow key={member.id}>
                    <TableCell className="font-medium text-slate-900">{member.name}</TableCell>
                    <TableCell className="capitalize text-sm">{member.status ?? '—'}</TableCell>
                    <TableCell className="text-sm text-slate-500">{member.stage ?? '—'}</TableCell>
                    <TableCell className="text-sm text-slate-500">{formatDate(member.joined_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        ) : (
          <p className="text-sm text-slate-500">No recent members yet.</p>
        )}
      </Card>
    </section>
  );
}

function DistributionList({
  items,
  labelKey,
  emptyLabel,
}: {
  items: Array<{ [key: string]: string | number | null }>;
  labelKey: string;
  emptyLabel: string;
}) {
  if (!items.length) {
    return <p className="text-sm text-slate-500">{emptyLabel}</p>;
  }

  const max = Math.max(...items.map((item) => Number(item.total ?? 0)), 1);

  return (
    <ul className="space-y-3">
      {items.map((item, index) => {
        const label = String(item[labelKey] ?? 'Unknown');
        const total = Number(item.total ?? 0);
        const percentage = Math.round((total / max) * 100);

        return (
          <li key={`${label}-${index}`} className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-500">
              <span className="capitalize">{label}</span>
              <span>{total}</span>
            </div>
            <div className="h-2 rounded-full bg-slate-200">
              <div
                className="h-full rounded-full bg-emerald-500 transition-all"
                style={{ width: `${percentage}%` }}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

function TrendChart({ data }: { data: Array<{ label: string; total: number }> }) {
  if (!data.length) {
    return null;
  }

  const max = Math.max(...data.map((item) => item.total), 1);
  const points = data.map((item, index) => {
    const x = data.length === 1 ? 0 : (index / (data.length - 1)) * 100;
    const y = 100 - (item.total / max) * 100;
    return `${x},${y}`;
  });

  return (
    <div className="space-y-3">
      <svg viewBox="0 0 100 100" className="h-40 w-full">
        <polyline
          fill="none"
          strokeWidth={2}
          stroke="currentColor"
          className="text-emerald-500"
          points={points.join(' ')}
        />
        {data.map((item, index) => {
          const x = data.length === 1 ? 0 : (index / (data.length - 1)) * 100;
          const y = 100 - (item.total / max) * 100;
          return <circle key={item.label} cx={x} cy={y} r={1.5} className="fill-emerald-500" />;
        })}
      </svg>
      <div className="grid grid-cols-3 gap-2 text-xs text-slate-500 md:grid-cols-6">
        {data.map((item) => (
          <span key={item.label}>{item.label}</span>
        ))}
      </div>
    </div>
  );
}

function formatDate(value: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return date.toLocaleDateString();
}
