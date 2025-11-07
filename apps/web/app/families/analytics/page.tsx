'use client';

import { FormEvent, useMemo, useState } from 'react';
import Link from 'next/link';
import {
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
import { AnalyticsBarChartCard } from '@/components/analytics/widgets';
import { useFamilyAnalytics } from '@/hooks/use-family-analytics';
import { useTenantId } from '@/lib/tenant';
import { FamilyAnalyticsFilters, buildFamilyAnalyticsExportUrl } from '@/lib/api/families';
import { getApiBaseUrl } from '@/lib/api/env';
import { downloadFromApi } from '@/lib/download';

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

export default function FamilyAnalyticsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [filters, setFilters] = useState<FamilyAnalyticsFilters>({});
  const [quickFilter, setQuickFilter] = useState<string | null>(null);
  const { data, isLoading } = useFamilyAnalytics(filters);

  const availableCities = useMemo(() => data?.filters?.cities ?? [], [data?.filters?.cities]);
  const availableStates = useMemo(() => data?.filters?.states ?? [], [data?.filters?.states]);
  const createdRange = data?.filters?.created_range ?? {};
  const totals = data?.totals;
  const totalFamiliesRaw = totals?.families;
  const totalFamilies = typeof totalFamiliesRaw === 'number' ? totalFamiliesRaw : 0;
  const familiesWithPrimaryRaw = totals?.families_with_primary_contact;
  const familiesWithPrimary =
    typeof familiesWithPrimaryRaw === 'number' ? familiesWithPrimaryRaw : null;
  const familiesWithoutPrimaryRaw = totals?.families_without_primary_contact;
  const familiesWithoutPrimary =
    typeof familiesWithoutPrimaryRaw === 'number' ? familiesWithoutPrimaryRaw : null;
  const primaryCoveragePct =
    familiesWithPrimary !== null && totalFamilies > 0
      ? Math.round((familiesWithPrimary / totalFamilies) * 100)
      : null;
  const missingPrimaryPct =
    familiesWithoutPrimary !== null && totalFamilies > 0
      ? Math.round((familiesWithoutPrimary / totalFamilies) * 100)
      : null;
  const newThisMonthRaw = totals?.new_this_month;
  const newThisMonth = typeof newThisMonthRaw === 'number' ? newThisMonthRaw : null;
  const newLastMonthRaw = totals?.new_last_month;
  const newLastMonth = typeof newLastMonthRaw === 'number' ? newLastMonthRaw : null;
  const growthVsLastMonthRaw = totals?.growth_vs_last_month;
  const growthVsLastMonth =
    typeof growthVsLastMonthRaw === 'number' ? growthVsLastMonthRaw : null;
  const largestHouseholdRaw = totals?.largest_household;
  const largestHouseholdHelper =
    typeof largestHouseholdRaw === 'number' && largestHouseholdRaw > 0
      ? `Largest household: ${largestHouseholdRaw}`
      : undefined;
  const withoutChildrenRaw = totals?.families_without_children;
  const withoutChildren =
    typeof withoutChildrenRaw === 'number' ? withoutChildrenRaw : null;
  const withChildrenHelper =
    withoutChildren !== null ? `Without children: ${withoutChildren}` : undefined;
  const totalsRecord = totals as Record<string, unknown> | undefined;
  const familiesWithEmergencyRawValue = totalsRecord?.['families_with_emergency_contact'];
  const familiesWithEmergencyRaw =
    typeof familiesWithEmergencyRawValue === 'number'
      ? (familiesWithEmergencyRawValue as number)
      : null;
  const growthDescriptor =
    growthVsLastMonth !== null ? `${growthVsLastMonth > 0 ? '+' : ''}${growthVsLastMonth}% vs last month` : null;
  const lastMonthDescriptor =
    newLastMonth !== null ? `Last month: ${newLastMonth}` : null;
  const newHouseholdsHelper =
    [growthDescriptor, lastMonthDescriptor].filter(Boolean).join(' • ') || undefined;

  const quickFilters = useMemo(() => {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const isoThirty = thirtyDaysAgo.toISOString().slice(0, 10);

    const base = [
      {
        id: 'primary:missing',
        label: 'Missing primary contact',
        helper: 'Households without a primary contact',
        build: (): FamilyAnalyticsFilters => ({ with_primary_contact: false }),
      },
      {
        id: 'primary:present',
        label: 'Has primary contact',
        helper: 'Households with assigned primary contact',
        build: (): FamilyAnalyticsFilters => ({ with_primary_contact: true }),
      },
      {
        id: 'children:with',
        label: 'Has children',
        helper: 'Includes child or dependent relationships',
        build: (): FamilyAnalyticsFilters => ({ with_children: true }),
      },
      {
        id: 'size:large',
        label: '4+ members',
        helper: 'Minimum household size of four',
        build: (): FamilyAnalyticsFilters => ({ min_members: 4 }),
      },
      {
        id: 'created:30',
        label: 'Created last 30 days',
        helper: 'Families created within the last 30 days',
        build: (): FamilyAnalyticsFilters => ({ created_from: isoThirty }),
      },
    ];

    const cityQuickFilter = availableCities[0]
      ? [{
          id: `city:${availableCities[0]}`,
          label: `City: ${availableCities[0]}`,
          helper: `Households in ${availableCities[0]}`,
          build: (): FamilyAnalyticsFilters => ({ city: availableCities[0] }),
        }]
      : [];

    const stateQuickFilter = availableStates[0]
      ? [{
          id: `state:${availableStates[0]}`,
          label: `State: ${availableStates[0]}`,
          helper: `Households in ${availableStates[0]}`,
          build: (): FamilyAnalyticsFilters => ({ state: availableStates[0] }),
        }]
      : [];

    return [...base, ...cityQuickFilter, ...stateQuickFilter];
  }, [availableCities, availableStates]);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);

    const minMembersRaw = formData.get('min_members') as string;
    const maxMembersRaw = formData.get('max_members') as string;
    const withPrimaryRaw = formData.get('with_primary_contact') as string | null;
    const withChildrenRaw = formData.get('with_children') as string | null;

    setQuickFilter(null);
    setFilters({
      min_members: minMembersRaw ? Number(minMembersRaw) : undefined,
      max_members: maxMembersRaw ? Number(maxMembersRaw) : undefined,
      with_primary_contact:
        withPrimaryRaw === ''
          ? null
          : withPrimaryRaw === 'true'
          ? true
          : withPrimaryRaw === 'false'
          ? false
          : undefined,
      with_children:
        withChildrenRaw === ''
          ? null
          : withChildrenRaw === 'true'
          ? true
          : withChildrenRaw === 'false'
          ? false
          : undefined,
      city: ((formData.get('city') as string) || undefined) ?? undefined,
      state: ((formData.get('state') as string) || undefined) ?? undefined,
      created_from: (formData.get('created_from') as string) || undefined,
      created_to: (formData.get('created_to') as string) || undefined,
    });
  };

  const handleReset = () => {
    setFilters({});
    setQuickFilter(null);
  };

  const handleQuickFilter = (filterId: string) => {
    if (filterId === quickFilter) {
      handleReset();
      return;
    }

    const config = quickFilters.find((item) => item.id === filterId);
    if (!config) {
      return;
    }

    setQuickFilter(filterId);
    setFilters(config.build());
  };

  const handleExport = async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const path = buildFamilyAnalyticsExportUrl(filters);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, 'family-analytics.csv');
      pushToast({ title: 'Export ready', description: 'Analytics CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  };

  const activeFilters = useMemo(() => {
    const chips: string[] = [];

    if (typeof filters.min_members === 'number') {
      chips.push(`Min members: ${filters.min_members}`);
    }

    if (typeof filters.max_members === 'number') {
      chips.push(`Max members: ${filters.max_members}`);
    }

    if (typeof filters.with_primary_contact === 'boolean') {
      chips.push(filters.with_primary_contact ? 'Has primary contact' : 'Missing primary contact');
    }

    if (typeof filters.with_children === 'boolean') {
      chips.push(filters.with_children ? 'Has children' : 'No children');
    }

    if (filters.city) {
      chips.push(`City: ${filters.city}`);
    }

    if (filters.state) {
      chips.push(`State: ${filters.state}`);
    }

    if (filters.created_from) {
      chips.push(`Created from ${filters.created_from}`);
    }

    if (filters.created_to) {
      chips.push(`Created to ${filters.created_to}`);
    }

    return chips;
  }, [filters]);

  const hasFilters = activeFilters.length > 0;

  if (isLoading) {
    return <p className="text-slate-500">Loading family analytics…</p>;
  }

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Family analytics</h2>
          <p className="text-sm text-slate-500">Understand household composition and recent family registrations.</p>
        </div>
        <Link href="/families" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to families
        </Link>
      </div>

      <Card className="space-y-3 border border-emerald-100 bg-emerald-50/40">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p className="text-sm font-semibold text-emerald-800">Quick filters</p>
            <p className="text-xs text-emerald-700/80">Jump straight to common household segments.</p>
          </div>
          <div className="flex items-center gap-2">
            {quickFilter ? (
              <Button type="button" size="sm" variant="ghost" onClick={handleReset}>
                Clear selection
              </Button>
            ) : null}
            <Button type="button" size="sm" variant="secondary" onClick={handleExport}>
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
          <p className="text-xs text-slate-400">No filters applied. Use the quick filters or form below to refine the analytics.</p>
        )}
      </div>

      <form
        key={JSON.stringify(filters)}
        className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4"
        onSubmit={handleSubmit}
      >
        <div>
          <Label htmlFor="min_members">Min members</Label>
          <Input id="min_members" name="min_members" type="number" min={0} defaultValue={filters.min_members ?? ''} />
        </div>
        <div>
          <Label htmlFor="max_members">Max members</Label>
          <Input id="max_members" name="max_members" type="number" min={0} defaultValue={filters.max_members ?? ''} />
        </div>
        <div>
          <Label htmlFor="with_primary_contact">Primary contact</Label>
          <Select
            id="with_primary_contact"
            name="with_primary_contact"
            defaultValue={typeof filters.with_primary_contact === 'boolean' ? String(filters.with_primary_contact) : ''}
          >
            <option value="">Any</option>
            <option value="true">Has primary contact</option>
            <option value="false">Missing primary contact</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="city">City</Label>
          <Select id="city" name="city" defaultValue={filters.city ?? ''}>
            <option value="">All cities</option>
            {availableCities.map((city) => (
              <option key={city} value={city}>
                {city}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="state">State/Region</Label>
          <Select id="state" name="state" defaultValue={filters.state ?? ''}>
            <option value="">All regions</option>
            {availableStates.map((state) => (
              <option key={state} value={state}>
                {state}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="created_from">Created from</Label>
          <Input
            id="created_from"
            name="created_from"
            type="date"
            defaultValue={filters.created_from ?? ''}
            min={createdRange.earliest ?? undefined}
            max={createdRange.latest ?? undefined}
          />
        </div>
        <div>
          <Label htmlFor="created_to">Created to</Label>
          <Input
            id="created_to"
            name="created_to"
            type="date"
            defaultValue={filters.created_to ?? ''}
            min={createdRange.earliest ?? undefined}
            max={createdRange.latest ?? undefined}
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

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <StatCard title="Total households" value={totals?.families ?? '—'} />
        <StatCard
          title="Average household size"
          value={totals?.average_household_size ?? '—'}
          helperText={largestHouseholdHelper}
        />
        <StatCard
          title="Families with children"
          value={totals?.families_with_children ?? '—'}
          helperText={withChildrenHelper}
        />
        <StatCard
          title="Primary contact assigned"
          value={familiesWithPrimary ?? '—'}
          helperText={
            primaryCoveragePct !== null ? `${primaryCoveragePct}% coverage` : undefined
          }
          tone={
            familiesWithPrimary !== null && familiesWithPrimary < totalFamilies
              ? 'warning'
              : 'success'
          }
        />
        <StatCard
          title="Missing primary contact"
          value={familiesWithoutPrimary ?? '—'}
          helperText={
            missingPrimaryPct !== null ? `${missingPrimaryPct}% of households` : undefined
          }
          tone={(familiesWithoutPrimary ?? 0) > 0 ? 'warning' : 'default'}
        />
        <StatCard
          title="New households this month"
          value={newThisMonth ?? '—'}
          helperText={newHouseholdsHelper}
          tone={growthVsLastMonth !== null && growthVsLastMonth > 0 ? 'success' : 'default'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <AnalyticsBarChartCard
          title="Household sizes"
          helperText="Distribution by household size"
          data={(data?.size_distribution ?? []).map((item) => ({
            label: String(item.label ?? '—'),
            value: Number(item.total ?? 0),
          }))}
        />
        <AnalyticsBarChartCard
          title="Relationships"
          helperText="Roles represented across families"
          data={(data?.by_relationship ?? []).map((item) => ({
            label: String(item.relationship ?? '—'),
            value: Number(item.total ?? 0),
          }))}
        />
      </div>

      <Card className="space-y-4">
        <header>
          <h3 className="text-lg font-semibold text-slate-900">Contact coverage health</h3>
          <p className="text-xs text-slate-500">
            Track how many households have key contacts assigned.
          </p>
        </header>
        <ContactCoverageList
          total={totalFamilies}
          withPrimary={familiesWithPrimary ?? undefined}
          withoutPrimary={familiesWithoutPrimary ?? undefined}
          withEmergency={
            typeof familiesWithEmergencyRaw === 'number' ? familiesWithEmergencyRaw : undefined
          }
        />
      </Card>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Recently created families</h3>
          <p className="text-xs text-slate-500">Latest five households</p>
        </header>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Family</TableHeaderCell>
                <TableHeaderCell>Members</TableHeaderCell>
                <TableHeaderCell>Created</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data?.recent_families ?? []).length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-4 text-center text-sm text-slate-500">
                    No recent families.
                  </TableCell>
                </TableRow>
              ) : (
                (data?.recent_families ?? []).map((family) => (
                  <TableRow key={family.id}>
                    <TableCell className="font-medium text-slate-900">{family.family_name}</TableCell>
                    <TableCell>{family.members_count}</TableCell>
                    <TableCell className="text-sm text-slate-500">
                      {family.created_at ? new Date(family.created_at).toLocaleDateString() : '—'}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Needs primary contact</h3>
          <p className="text-xs text-slate-500">Households missing an assigned primary contact</p>
        </header>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Family</TableHeaderCell>
                <TableHeaderCell>Members</TableHeaderCell>
                <TableHeaderCell>Created</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data?.families_missing_primary ?? []).length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-4 text-center text-sm text-slate-500">
                    All households have a designated primary contact.
                  </TableCell>
                </TableRow>
              ) : (
                (data?.families_missing_primary ?? []).map((family) => (
                  <TableRow key={family.id}>
                    <TableCell className="font-medium text-slate-900">{family.family_name}</TableCell>
                    <TableCell>{family.members_count}</TableCell>
                    <TableCell className="text-sm text-slate-500">
                      {family.created_at ? new Date(family.created_at).toLocaleDateString() : '—'}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>
    </section>
  );
}

function ContactCoverageList({
  total,
  withPrimary,
  withoutPrimary,
  withEmergency,
}: {
  total: number;
  withPrimary?: number;
  withoutPrimary?: number;
  withEmergency?: number;
}) {
  const items: Array<{
    key: string;
    label: string;
    value?: number;
    color: string;
  }> = [];

  if (typeof withPrimary === 'number') {
    items.push({
      key: 'with-primary',
      label: 'Primary contact assigned',
      value: withPrimary,
      color: 'bg-emerald-500',
    });
  }

  if (typeof withoutPrimary === 'number') {
    items.push({
      key: 'without-primary',
      label: 'Missing primary contact',
      value: withoutPrimary,
      color: 'bg-amber-500',
    });
  }

  if (typeof withEmergency === 'number') {
    items.push({
      key: 'with-emergency',
      label: 'Emergency contact assigned',
      value: withEmergency,
      color: 'bg-cyan-500',
    });
  }

  if (!items.length) {
    return <p className="text-sm text-slate-500">No contact coverage data available.</p>;
  }

  return (
    <ul className="space-y-3">
      {items.map((item) => {
        const pct =
          total > 0 && typeof item.value === 'number'
            ? Math.round((item.value / total) * 100)
            : null;

        return (
          <li key={item.key} className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-500">
              <span>{item.label}</span>
              <span className="font-medium text-slate-700">
                {typeof item.value === 'number' ? item.value : '—'}
                {pct !== null ? ` • ${pct}%` : ''}
              </span>
            </div>
            <div className="h-2 rounded-full bg-slate-200">
              <div
                className={`h-full rounded-full transition-all ${item.color}`}
                style={{ width: `${pct ?? 0}%` }}
                aria-hidden="true"
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}
