'use client';

import Link from 'next/link';
import { FormEvent, Suspense, useCallback, useMemo } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { MembersTable } from '@/components/members/members-table';
import { useMembers } from '@/hooks/use-members';
import { Button, Input, Label, Select, useToast } from '@church/ui';
import { getApiBaseUrl } from '@/lib/api/env';
import { downloadFromApi } from '@/lib/download';
import type { MemberFilters } from '@/lib/api/members';
import { useTenantId } from '@/lib/tenant';

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'prospect', label: 'Prospect' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'visitor', label: 'Visitor' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'transferred', label: 'Transferred' },
];

const COLUMN_OPTIONS = [
  { id: 'preferred_name', label: 'Preferred name' },
  { id: 'status', label: 'Status' },
  { id: 'stage', label: 'Membership stage' },
  { id: 'contact', label: 'Primary contact' },
];

const DEFAULT_VISIBLE_COLUMNS = ['status', 'preferred_name', 'contact'];

function MembersContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const tenantId = useTenantId();
  const { pushToast } = useToast();

  const status = searchParams.get('status') ?? '';
  const search = searchParams.get('q') ?? '';
  const page = Math.max(1, Number(searchParams.get('page') ?? '') || 1);
  const perPage = Math.max(1, Number(searchParams.get('per_page') ?? '') || 10);
  const sort = searchParams.get('sort') ?? 'last_name';
  const direction = searchParams.get('direction') === 'desc' ? 'desc' : 'asc';
  const columnsParam = searchParams.get('columns');

  const visibleColumns = useMemo(() => {
    if (!columnsParam) {
      return DEFAULT_VISIBLE_COLUMNS;
    }

    const columns = columnsParam
      .split(',')
      .map((column) => column.trim())
      .filter(Boolean);

    if (columns.length === 0) {
      return DEFAULT_VISIBLE_COLUMNS;
    }

    return Array.from(new Set(columns));
  }, [columnsParam]);

  const filters = useMemo<MemberFilters>(
    () => ({
      status: status || undefined,
      search: search || undefined,
      page,
      per_page: perPage,
      sort,
      direction,
    }),
    [status, search, page, perPage, sort, direction]
  );

  const { data: membersResponse, isLoading, isFetching } = useMembers(filters);
  const members = membersResponse?.data ?? [];
  const meta = membersResponse?.meta;

  const updateParams = useCallback(
    (next: Record<string, string | undefined>) => {
      const params = new URLSearchParams(searchParams.toString());
      Object.entries(next).forEach(([key, value]) => {
        if (value && value.length > 0) {
          params.set(key, value);
        } else {
          params.delete(key);
        }
      });
      const query = params.toString();
      router.push(`${pathname}${query ? `?${query}` : ''}`);
    },
    [pathname, router, searchParams]
  );

  const handleSubmit = useCallback(
    (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      updateParams({
        q: (formData.get('q') as string) ?? undefined,
        status: (formData.get('status') as string) ?? undefined,
        page: '1',
      });
    },
    [updateParams]
  );

  const handleReset = useCallback(() => {
    updateParams({ q: undefined, status: undefined, page: undefined, per_page: undefined });
  }, [updateParams]);

  const handlePageChange = useCallback(
    (nextPage: number) => {
      updateParams({ page: String(nextPage) });
    },
    [updateParams]
  );

  const handlePerPageChange = useCallback(
    (value: number) => {
      updateParams({ per_page: String(value), page: '1' });
    },
    [updateParams]
  );

  const handleSortChange = useCallback(
    (column: string, nextDirection: 'asc' | 'desc') => {
      updateParams({ sort: column, direction: nextDirection, page: '1' });
    },
    [updateParams]
  );

  const handleColumnToggle = useCallback(
    (columnId: string) => {
      const set = new Set(visibleColumns);
      if (set.has(columnId)) {
        set.delete(columnId);
      } else {
        set.add(columnId);
      }

      if (set.size === 0) {
        return;
      }

      updateParams({ columns: Array.from(set).join(',') || undefined });
    },
    [updateParams, visibleColumns]
  );

  const handleExport = useCallback(async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const params = new URLSearchParams();
      if (filters.search) params.set('search', filters.search);
      if (filters.status) params.set('status', filters.status);
      params.set('sort', filters.sort ?? 'last_name');
      params.set('direction', filters.direction ?? 'asc');
      const url = `${getApiBaseUrl()}/v1/members/export${params.size ? `?${params}` : ''}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, 'members-export.csv');
      pushToast({ title: 'Export ready', description: 'Member CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  }, [tenantId, filters, pushToast]);

  return (
    <section className="space-y-6">
      <header className="space-y-4">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Members</h2>
          <p className="text-sm text-slate-500">Overview of all people connected to your church.</p>
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          <Link href="/families/new" className="text-sm text-emerald-600 hover:text-emerald-700">
            Create family
          </Link>
          <Link href="/members/analytics">
            <Button variant="ghost">Analytics</Button>
          </Link>
          <Link href="/members/reports">
            <Button variant="ghost">Saved reports</Button>
          </Link>
          <Link href="/members/custom-fields">
            <Button variant="ghost">Custom fields</Button>
          </Link>
          <Button variant="secondary" onClick={handleExport}>
            Export CSV
          </Button>
          <Link href="/members/new">
            <Button>New Member</Button>
          </Link>
        </div>

        <form
          className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4"
          onSubmit={handleSubmit}
        >
          <div className="md:col-span-2">
            <Label htmlFor="member-search">Search</Label>
            <Input
              id="member-search"
              name="q"
              defaultValue={search}
              placeholder="Search by name or stage"
            />
          </div>
          <div>
            <Label htmlFor="member-status">Status</Label>
            <Select id="member-status" name="status" defaultValue={status}>
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-end gap-2">
            <Button type="submit">Apply</Button>
            <Button type="button" variant="ghost" onClick={handleReset}>
              Reset
            </Button>
          </div>
        </form>
      </header>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 grid gap-4 md:grid-cols-3">
          <div className="md:col-span-2">
            <p className="text-sm font-medium text-slate-700">Columns</p>
            <div className="mt-2 flex flex-wrap gap-4">
              {COLUMN_OPTIONS.map((column) => (
                <label key={column.id} className="flex items-center gap-2 text-sm text-slate-600">
                  <input
                    type="checkbox"
                    checked={visibleColumns.includes(column.id)}
                    onChange={() => handleColumnToggle(column.id)}
                  />
                  {column.label}
                </label>
              ))}
            </div>
          </div>
          <div>
            <Label htmlFor="members-per-page">Rows per page</Label>
            <Select
              id="members-per-page"
              value={String(perPage)}
              onChange={(event) => handlePerPageChange(Number(event.target.value))}
            >
              {[10, 25, 50].map((option) => (
                <option key={option} value={option}>
                  {option} per page
                </option>
              ))}
            </Select>
          </div>
        </div>

        <MembersTable
          members={members}
          meta={meta}
          isLoading={isLoading || isFetching}
          sort={sort}
          direction={direction}
          visibleColumns={visibleColumns}
          onSortChange={handleSortChange}
          onPageChange={handlePageChange}
        />
      </div>
    </section>
  );
}

export default function MembersPage() {
  return (
    <Suspense fallback={<p className="text-slate-500">Loading members…</p>}>
      <MembersContent />
    </Suspense>
  );
}
