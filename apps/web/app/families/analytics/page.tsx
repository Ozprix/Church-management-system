'use client';

import { FormEvent, useState } from 'react';
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
  const { data, isLoading } = useFamilyAnalytics(filters);

  const availableCities = data?.filters?.cities ?? [];
  const availableStates = data?.filters?.states ?? [];
  const createdRange = data?.filters?.created_range ?? {};

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);

    const minMembersRaw = formData.get('min_members') as string;
    const maxMembersRaw = formData.get('max_members') as string;
    const withPrimaryRaw = formData.get('with_primary_contact') as string | null;

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
      city: ((formData.get('city') as string) || undefined) ?? undefined,
      state: ((formData.get('state') as string) || undefined) ?? undefined,
      created_from: (formData.get('created_from') as string) || undefined,
      created_to: (formData.get('created_to') as string) || undefined,
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

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard title="Total households" value={data?.totals.families ?? '—'} />
        <StatCard title="Average household size" value={data?.totals.average_household_size ?? '—'} />
        <StatCard title="Families with children" value={data?.totals.families_with_children ?? '—'} />
        <StatCard
          title="Missing primary contact"
          value={data?.totals.families_without_primary_contact ?? '—'}
          tone={(data?.totals.families_without_primary_contact ?? 0) > 0 ? 'warning' : 'default'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Household sizes</h3>
            <p className="text-xs text-slate-500">Distribution by household size</p>
          </header>
          <DistributionTable data={data?.size_distribution ?? []} labelKey="label" />
        </Card>
        <Card className="space-y-3">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Relationships</h3>
            <p className="text-xs text-slate-500">Roles across families</p>
          </header>
          <DistributionTable data={data?.by_relationship ?? []} labelKey="relationship" />
        </Card>
      </div>

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

function DistributionTable({
  data,
  labelKey,
}: {
  data: Array<Record<string, unknown>>;
  labelKey: string;
}) {
  if (data.length === 0) {
    return <p className="text-sm text-slate-500">No data available.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Label</TableHeaderCell>
            <TableHeaderCell>Total</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {data.map((item, index) => (
            <TableRow key={index}>
              <TableCell className="capitalize">{String(item[labelKey] ?? '—')}</TableCell>
              <TableCell>{String(item.total ?? '0')}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
