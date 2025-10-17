'use client';

import { FormEvent, useMemo, useState } from 'react';
import Link from 'next/link';
import {
  Button,
  Card,
  Input,
  Label,
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
import { getApiBaseUrl } from '@/lib/api/env';
import { downloadFromApi } from '@/lib/download';
import { useFamilies } from '@/hooks/use-families';
import { useFamilyDashboard } from '@/hooks/use-family-dashboard';
import { useTenantId } from '@/lib/tenant';

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

export default function FamiliesPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [searchInput, setSearchInput] = useState('');
  const [filters, setFilters] = useState<{ search?: string; page?: number; per_page: number }>({
    per_page: 25,
  });

  const filtersMemo = useMemo(() => filters, [filters]);
  const { data: familiesResponse, isLoading: isFamiliesLoading } = useFamilies(filtersMemo);
  const { data: dashboard } = useFamilyDashboard();

  const families = familiesResponse?.data ?? [];
  const meta = familiesResponse?.meta ?? {};
  const currentPage = meta.current_page ?? 1;
  const lastPage = meta.last_page ?? 1;

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFilters((prev) => ({
      ...prev,
      search: searchInput.trim() || undefined,
      page: 1,
    }));
  };

  const handleExport = async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const params = new URLSearchParams();
      if (filtersMemo.search) {
        params.set('search', filtersMemo.search);
      }
      const query = params.toString();
      const url = `${getApiBaseUrl()}/v1/families/export${query ? `?${query}` : ''}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, 'families-export.csv');
      pushToast({ title: 'Export ready', description: 'Family CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  };

  const handlePageChange = (direction: 'prev' | 'next') => {
    setFilters((prev) => {
      const nextPage = direction === 'prev' ? Math.max((prev.page ?? 1) - 1, 1) : Math.min((prev.page ?? 1) + 1, lastPage);
      return { ...prev, page: nextPage };
    });
  };

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Families</h2>
          <p className="text-sm text-slate-500">View household insights and manage family records.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Button variant="secondary" onClick={handleExport}>
            Export CSV
          </Button>
          <Link href="/families/new">
            <Button>New family</Button>
          </Link>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Total families"
          value={dashboard?.stats.total_families ?? '—'}
        />
        <StatCard
          title="Individuals in households"
          value={dashboard?.stats.total_individuals ?? '—'}
        />
        <StatCard
          title="Primary contacts"
          value={dashboard?.stats.families_with_primary_contact ?? '—'}
          helperText={`Without primary: ${dashboard?.stats.families_without_primary_contact ?? 0}`}
        />
        <StatCard
          title="Emergency contacts"
          value={dashboard?.stats.families_with_emergency_contact ?? '—'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Households by city</h3>
            <p className="text-xs text-slate-500">Top locations</p>
          </header>
          <DistributionList
            items={dashboard?.by_city ?? []}
            labelKey="city"
            emptyLabel="No city data yet."
          />
        </Card>

        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">New families trend</h3>
            <p className="text-xs text-slate-500">Last 8 weeks</p>
          </header>
          {dashboard?.new_families_trend?.length ? (
            <TrendChart data={dashboard.new_families_trend} />
          ) : (
            <p className="text-sm text-slate-500">No recent activity.</p>
          )}
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Follow-up reminders</h3>
            <p className="text-xs text-slate-500">Primary and emergency contacts</p>
          </header>
          <p className="text-xs text-slate-500">
            Missing primary contacts: {dashboard?.reminders?.missing_primary_contact_total ?? 0} • Missing emergency contacts:{' '}
            {dashboard?.reminders?.missing_emergency_contact_total ?? 0}
          </p>
          {dashboard?.reminders?.suggested_families?.length ? (
            <ul className="space-y-2 text-sm text-slate-600">
              {dashboard.reminders.suggested_families.map((family) => (
                <li key={family.id} className="flex items-center justify-between">
                  <span>{family.family_name}</span>
                  <Link href={`/families/${family.id}`} className="text-emerald-600 hover:text-emerald-700">
                    review
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-slate-500">All households have primary and emergency contacts.</p>
          )}
        </Card>

        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Upcoming anniversaries</h3>
            <p className="text-xs text-slate-500">Next 45 days</p>
          </header>
          {dashboard?.upcoming_anniversaries?.length ? (
            <ul className="space-y-2 text-sm text-slate-600">
              {dashboard.upcoming_anniversaries.map((anniversary) => (
                <li key={anniversary.id} className="flex items-center justify-between">
                  <span>{anniversary.family_name}</span>
                  <span className="text-xs text-slate-500">{formatAnniversary(anniversary)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-slate-500">No anniversaries on the horizon.</p>
          )}
        </Card>
      </div>

      <Card className="space-y-4">
        <form className="flex flex-col gap-3 md:flex-row md:items-end" onSubmit={handleSearch}>
          <div className="flex-1">
            <Label htmlFor="family-search">Search families</Label>
            <Input
              id="family-search"
              placeholder="Search by family name"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <Button type="submit" variant="secondary">
              Filter
            </Button>
            <Button
              type="button"
              variant="ghost"
              onClick={() => {
                setSearchInput('');
                setFilters((prev) => ({ ...prev, search: undefined, page: 1 }));
              }}
            >
              Reset
            </Button>
          </div>
        </form>

        {isFamiliesLoading ? (
          <p className="text-sm text-slate-500">Loading families…</p>
        ) : families.length === 0 ? (
          <p className="text-sm text-slate-500">No families found for the current filters.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Family</TableHeaderCell>
                  <TableHeaderCell className="text-right">Members</TableHeaderCell>
                  <TableHeaderCell>City</TableHeaderCell>
                  <TableHeaderCell>Created</TableHeaderCell>
                  <TableHeaderCell></TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {families.map((family) => (
                  <TableRow key={family.id}>
                    <TableCell className="font-medium text-slate-900">{family.family_name}</TableCell>
                    <TableCell className="text-right text-sm">{family.members_count ?? '—'}</TableCell>
                    <TableCell className="text-sm text-slate-500">{extractString(family.address?.['city']) ?? '—'}</TableCell>
                    <TableCell className="text-sm text-slate-500">{formatDate(family)}</TableCell>
                    <TableCell>
                      <Link href={`/families/${family.id}`} className="text-sm text-emerald-600 hover:text-emerald-700">
                        View
                      </Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}

        <div className="flex items-center justify-between text-sm text-slate-500">
          <span>
            Page {currentPage} of {lastPage}
          </span>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={currentPage <= 1}
              onClick={() => handlePageChange('prev')}
            >
              Previous
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={currentPage >= lastPage}
              onClick={() => handlePageChange('next')}
            >
              Next
            </Button>
          </div>
        </div>
      </Card>

      <Card className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Recent families</h3>
          <p className="text-xs text-slate-500">Latest additions</p>
        </div>
        {dashboard?.recent_families?.length ? (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Family</TableHeaderCell>
                  <TableHeaderCell className="text-right">Members</TableHeaderCell>
                  <TableHeaderCell>Created</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {dashboard.recent_families.map((family) => (
                  <TableRow key={family.id}>
                    <TableCell className="font-medium text-slate-900">{family.family_name}</TableCell>
                    <TableCell className="text-right text-sm">{family.members_count}</TableCell>
                    <TableCell className="text-sm text-slate-500">{formatDate(family)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        ) : (
          <p className="text-sm text-slate-500">No recent families yet.</p>
        )}
      </Card>
    </section>
  );
}

function formatDate(record: { created_at?: string }): string {
  if (!record.created_at) {
    return '—';
  }
  const date = new Date(record.created_at);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return date.toLocaleDateString();
}

function extractString(value: unknown): string | null {
  if (typeof value === 'string' && value.trim().length > 0) {
    return value;
  }
  return null;
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
              <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${percentage}%` }} />
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
      <div className="grid grid-cols-4 gap-2 text-xs text-slate-500 md:grid-cols-8">
        {data.map((item) => (
          <span key={item.label}>{item.label}</span>
        ))}
      </div>
    </div>
  );
}

function formatAnniversary(record: { anniversary_on?: string; days_until?: number }): string {
  if (!record.anniversary_on) {
    return '—';
  }
  const date = new Date(record.anniversary_on);
  const formatted = Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString();
  const days = typeof record.days_until === 'number' ? `${record.days_until}d` : '';
  return days ? `${formatted} (${days})` : formatted;
}
