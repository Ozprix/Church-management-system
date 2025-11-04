'use client';

import { FormEvent, useMemo, useState } from 'react';
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
  const [quickFilter, setQuickFilter] = useState<string | null>(null);
  const { data, isLoading } = useMemberAnalytics(filters);

  const quickFilters = useMemo(() => {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const isoThirty = thirtyDaysAgo.toISOString().slice(0, 10);

    return [
      {
        id: 'status:active',
        label: 'Active members',
        helper: 'Status = active',
        build: (): MemberAnalyticsFilters => ({ status: 'active' }),
      },
      {
        id: 'status:visitor',
        label: 'Visitors',
        helper: 'Status = visitor',
        build: (): MemberAnalyticsFilters => ({ status: 'visitor' }),
      },
      {
        id: 'family:with',
        label: 'Has family',
        helper: 'Assigned to a household',
        build: (): MemberAnalyticsFilters => ({ with_family: true }),
      },
      {
        id: 'family:without',
        label: 'No family',
        helper: 'No household assignment',
        build: (): MemberAnalyticsFilters => ({ with_family: false }),
      },
      {
        id: 'recent:30',
        label: 'Joined last 30 days',
        helper: 'Joined_from = last 30 days',
        build: (): MemberAnalyticsFilters => ({ joined_from: isoThirty }),
      },
    ];
  }, []);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const status = (formData.get('status') as string) || undefined;
    const stage = (formData.get('stage') as string) || undefined;
    const joinedFrom = (formData.get('joined_from') as string) || undefined;
    const joinedTo = (formData.get('joined_to') as string) || undefined;
    const withFamilyRaw = formData.get('with_family') as string | null;
    const withFamily = withFamilyRaw === '' ? null : withFamilyRaw === 'true';

    setQuickFilter(null);
    setFilters({
      status,
      stage,
      joined_from: joinedFrom,
      joined_to: joinedTo,
      with_family: withFamily,
    });
  };

  const handleQuickFilter = (filterId: string) => {
    if (filterId === quickFilter) {
      handleResetFilters();
      return;
    }

    const config = quickFilters.find((item) => item.id === filterId);
    if (!config) {
      return;
    }

    setQuickFilter(filterId);
    setFilters(config.build());
  };

  const handleResetFilters = () => {
    setFilters({});
    setQuickFilter(null);
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

  const statusOptions = data?.filters?.statuses ?? [];
  const stageOptions = data?.filters?.stages ?? [];
  const joinedRange = data?.filters?.joined_range ?? {};
  const totals = data?.totals;
  const activeFilters = [
    filters.status ? `Status: ${filters.status}` : null,
    filters.stage ? `Stage: ${filters.stage}` : null,
    filters.joined_from ? `Joined from ${filters.joined_from}` : null,
    filters.joined_to ? `Joined to ${filters.joined_to}` : null,
    typeof filters.with_family === 'boolean'
      ? filters.with_family
        ? 'Has family'
        : 'No family'
      : null,
  ].filter(Boolean) as string[];
  const hasFilters = activeFilters.length > 0;

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

      <Card className="space-y-3 border border-emerald-100 bg-emerald-50/40">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p className="text-sm font-semibold text-emerald-800">Quick filters</p>
            <p className="text-xs text-emerald-700/80">Jump to common segments with a single tap.</p>
          </div>
          <div className="flex items-center gap-2">
            {quickFilter ? (
              <Button type="button" size="sm" variant="ghost" onClick={handleResetFilters}>
                Clear selection
              </Button>
            ) : null}
            <Button type="button" size="sm" variant="ghost" onClick={handleExport}>
              Export CSV
            </Button>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {quickFilters.map((item) => (
            <Button
              key={item.id}
              type="button"
              size="sm"
              variant={quickFilter === item.id ? 'primary' : 'secondary'}
              onClick={() => handleQuickFilter(item.id)}
            >
              {item.label}
            </Button>
          ))}
        </div>
        {quickFilter ? (
          <p className="text-xs text-emerald-700/70">
            {quickFilters.find((item) => item.id === quickFilter)?.helper ?? 'Quick filter active'}
          </p>
        ) : null}
      </Card>

      <div className="space-y-2">
        {hasFilters ? (
          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span className="font-semibold uppercase tracking-wide">Active filters:</span>
            {activeFilters.map((label) => (
              <span key={label} className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                {label}
              </span>
            ))}
          </div>
        ) : (
          <p className="text-xs text-slate-400">No filters applied. Use the quick filters or form to drill into segments.</p>
        )}
      </div>

      <form
        key={JSON.stringify(filters)}
        className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5"
        onSubmit={handleSubmit}
      >
        <div>
          <Label htmlFor="status">Status</Label>
          <Select id="status" name="status" defaultValue={filters.status ?? ''}>
            <option value="">All statuses</option>
            {statusOptions.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="stage">Stage</Label>
          <Select id="stage" name="stage" defaultValue={filters.stage ?? ''}>
            <option value="">All stages</option>
            {stageOptions.map((stage) => (
              <option key={stage} value={stage}>
                {stage}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="joined_from">Joined from</Label>
          <Input
            id="joined_from"
            name="joined_from"
            type="date"
            defaultValue={filters.joined_from ?? ''}
            min={joinedRange.earliest ?? undefined}
            max={joinedRange.latest ?? undefined}
          />
        </div>
        <div>
          <Label htmlFor="joined_to">Joined to</Label>
          <Input
            id="joined_to"
            name="joined_to"
            type="date"
            defaultValue={filters.joined_to ?? ''}
            min={joinedRange.earliest ?? undefined}
            max={joinedRange.latest ?? undefined}
          />
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
            onClick={handleResetFilters}
          >
            Reset
          </Button>
        </div>
      </form>

      <div className="grid gap-4 md:grid-cols-4">
        <StatCard title="Total members" value={formatNumber(totals?.members)} helperText="Across current filters" />
        <StatCard
          title="Conversion rate"
          value={formatPercent(totals?.conversion_rate)}
          helperText="Members who are beyond visitor status"
          tone={(totals?.conversion_rate ?? 0) < 30 ? 'warning' : 'default'}
        />
        <StatCard
          title="New this month"
          value={formatNumber(totals?.new_this_month)}
          helperText={`Last month ${formatNumber(totals?.new_last_month)}`}
        />
        <StatCard
          title="Growth vs last month"
          value={formatGrowth(totals?.growth_vs_last_month)}
          helperText="Based on new members month over month"
          tone={typeof totals?.growth_vs_last_month === 'number' && totals.growth_vs_last_month < 0 ? 'warning' : 'default'}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-4">
        <StatCard
          title="With family"
          value={formatNumber(totals?.members_with_family)}
          helperText="Members assigned to a household"
        />
        <StatCard
          title="Without family"
          value={formatNumber(totals?.members_without_family)}
          helperText="Members not yet linked to a household"
          tone={(totals?.members_without_family ?? 0) > 0 ? 'warning' : 'default'}
        />
        <StatCard
          title="Profiles to refresh"
          value={formatNumber(totals?.stale_profiles)}
          helperText="Haven’t been updated in 6+ months"
          tone={(totals?.stale_profiles ?? 0) > 0 ? 'warning' : 'default'}
        />
        <StatCard
          title="Recent visitors"
          value={formatNumber(totals?.recent_visitors)}
          helperText="Visitors added in the past 4 weeks"
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
                    <TableCell className="text-sm">
                      <StatusBadge value={member.status} />
                    </TableCell>
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

  const totalSum = items.reduce((acc, item) => acc + Number(item.total ?? 0), 0);
  const max = Math.max(...items.map((item) => Number(item.total ?? 0)), 1);

  return (
    <ul className="space-y-3">
      {items.map((item, index) => {
        const label = String(item[labelKey] ?? 'Unknown');
        const total = Number(item.total ?? 0);
        const percentageOfMax = Math.round((total / max) * 100);
        const share = totalSum > 0 ? Math.round((total / totalSum) * 100) : 0;

        return (
          <li key={`${label}-${index}`} className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-500">
              <span className="capitalize">{label}</span>
              <span>
                {total} • {share}%
              </span>
            </div>
            <div className="h-2 rounded-full bg-slate-200">
              <div
                className="h-full rounded-full bg-emerald-500 transition-all"
                style={{ width: `${percentageOfMax}%` }}
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
  const coordinates = data.map((item, index) => {
    const x = data.length === 1 ? 0 : (index / (data.length - 1)) * 100;
    const y = 100 - (item.total / max) * 100;
    return { x, y };
  });
  const points = coordinates.map((point) => `${point.x},${point.y}`).join(' ');
  const polygonPoints = [
    ...coordinates.map((point) => `${point.x},${point.y}`),
    `${coordinates[coordinates.length - 1].x},100`,
    `${coordinates[0].x},100`,
  ].join(' ');

  return (
    <div className="space-y-3">
      <svg viewBox="0 0 100 100" className="h-40 w-full">
        <defs>
          <linearGradient id="member-trend" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="rgba(16, 185, 129, 0.45)" />
            <stop offset="100%" stopColor="rgba(16, 185, 129, 0.05)" />
          </linearGradient>
        </defs>
        <polygon
          points={polygonPoints}
          fill="url(#member-trend)"
          opacity={0.6}
        />
        <polyline
          fill="none"
          strokeWidth={2}
          stroke="currentColor"
          className="text-emerald-500"
          points={points}
        />
        {coordinates.map((point, index) => {
          return <circle key={data[index].label} cx={point.x} cy={point.y} r={1.5} className="fill-emerald-500" />;
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

function formatNumber(value?: number | null): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return '—';
  }

  return value.toLocaleString();
}

function formatPercent(value?: number | null): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return '—';
  }

  return `${value.toFixed(1)}%`;
}

function formatGrowth(value?: number | null): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return '—';
  }

  const prefix = value > 0 ? '+' : '';
  return `${prefix}${value.toFixed(1)}%`;
}

function StatusBadge({ value }: { value?: string | null }) {
  if (!value) {
    return <span className="text-sm text-slate-500">—</span>;
  }

  const normalized = value.toLowerCase();
  const colorMap: Record<string, { bg: string; text: string }> = {
    active: { bg: 'bg-emerald-100', text: 'text-emerald-700' },
    visitor: { bg: 'bg-amber-100', text: 'text-amber-700' },
    prospect: { bg: 'bg-sky-100', text: 'text-sky-700' },
    inactive: { bg: 'bg-slate-200', text: 'text-slate-700' },
    suspended: { bg: 'bg-red-100', text: 'text-red-700' },
  };

  const colors = colorMap[normalized] ?? { bg: 'bg-slate-100', text: 'text-slate-700' };

  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${colors.bg} ${colors.text}`}
    >
      {normalized.replace(/_/g, ' ')}
    </span>
  );
}
