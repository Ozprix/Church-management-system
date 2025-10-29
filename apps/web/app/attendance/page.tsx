'use client';

import Link from 'next/link';
import { FormEvent, Suspense, useCallback, useMemo } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import {
  Button,
  Card,
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
import { useGatherings } from '@/hooks/use-gatherings';
import { useServices } from '@/hooks/use-services';
import { useAttendanceAnalytics } from '@/hooks/use-attendance-analytics';
import type { GatheringSummary } from '@/lib/api/gatherings';
import type { AttendanceAnalytics } from '@/lib/api/attendance';
import { buildAttendanceAnalyticsExportUrl, buildAttendanceBulkExportUrl, buildGatheringAttendanceExportUrl } from '@/lib/api/attendance';
import { downloadFromApi } from '@/lib/download';
import { getApiBaseUrl } from '@/lib/api/env';
import { useTenantId } from '@/lib/tenant';

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];

function AttendanceContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const tenantId = useTenantId();
  const { pushToast } = useToast();

  const status = searchParams.get('status') ?? '';
  const serviceId = searchParams.get('service_id');
  const page = Number(searchParams.get('page') ?? '1');

  const filters = useMemo(
    () => ({
      status: status || undefined,
      service_id: serviceId ? Number(serviceId) : undefined,
      page,
      per_page: 10,
    }),
    [status, serviceId, page]
  );

  const { data: gatheringsResponse, isLoading } = useGatherings(filters);
  const gatherings = gatheringsResponse?.data ?? [];
  const meta = gatheringsResponse?.meta;

  const { data: serviceResponse } = useServices({ per_page: 50 });
  const serviceOptions = serviceResponse?.data ?? [];
  const { data: analytics, isLoading: analyticsLoading } = useAttendanceAnalytics();
  const handleExport = useCallback(async () => {
    if (!tenantId) {
      pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const path = buildAttendanceAnalyticsExportUrl();
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      const token = process.env.NEXT_PUBLIC_API_TOKEN;
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }

      await downloadFromApi(url, { headers }, 'attendance-analytics.csv');
      pushToast({ title: 'Export ready', description: 'Attendance CSV download has started.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  }, [pushToast, tenantId]);

  const handleBulkExport = useCallback(
    async (format: 'csv' | 'pdf') => {
      if (!tenantId) {
        pushToast({ title: 'Unable to export', description: 'Missing tenant context', variant: 'error' });
        return;
      }

      const ids = gatherings.map((gathering) => gathering.id).filter((id): id is number => typeof id === 'number');
      if (!ids.length) {
        pushToast({ title: 'No gatherings to export', description: 'Adjust your filters and try again.', variant: 'error' });
        return;
      }

      try {
        const url = `${getApiBaseUrl()}${buildAttendanceBulkExportUrl()}`;
        const headers: HeadersInit = {
          'Content-Type': 'application/json',
          Accept: 'application/zip',
          'X-Tenant-ID': tenantId,
        };
        const token = process.env.NEXT_PUBLIC_API_TOKEN;
        if (token) {
          headers.Authorization = `Bearer ${token}`;
        }

        await downloadFromApi(
          url,
          {
            method: 'POST',
            headers,
            body: JSON.stringify({
              gathering_ids: ids,
              format,
            }),
          },
          `attendance-export-${format}.zip`
        );

        pushToast({
          title: 'Export ready',
          description: format === 'pdf' ? 'Attendance PDFs are downloading.' : 'Attendance CSV archive is downloading.',
          variant: 'success',
        });
      } catch (error) {
        const message = error instanceof Error ? error.message : 'Export failed';
        pushToast({ title: 'Export failed', description: message, variant: 'error' });
      }
    },
    [gatherings, pushToast, tenantId]
  );

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

  const handleFilters = useCallback(
    (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      updateParams({
        status: (formData.get('status') as string) ?? undefined,
        service_id: (formData.get('service_id') as string) ?? undefined,
        page: '1',
      });
    },
    [updateParams]
  );

  const handlePageChange = useCallback(
    (nextPage: number) => {
      updateParams({ page: String(nextPage) });
    },
    [updateParams]
  );

  const currentPage = meta?.current_page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  return (
    <section className="space-y-6">
      <header className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Attendance</h2>
          <p className="text-sm text-slate-500">Track gatherings, attendance trends, and follow up on absences.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          View member roster
        </Link>
      </header>

      <AttendanceAnalyticsSection data={analytics} isLoading={analyticsLoading} onExport={handleExport} />

      <Card className="space-y-4">
        <form className="grid gap-4 md:grid-cols-3" onSubmit={handleFilters}>
          <div>
            <Label htmlFor="gathering-status">Status</Label>
            <Select id="gathering-status" name="status" defaultValue={status}>
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="gathering-service">Service</Label>
            <Select id="gathering-service" name="service_id" defaultValue={serviceId ?? ''}>
              <option value="">All services</option>
              {serviceOptions.map((service) => (
                <option key={service.id} value={service.id}>
                  {service.name}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-end gap-2">
            <Button type="submit">Apply</Button>
            <Button type="button" variant="ghost" onClick={() => updateParams({ status: undefined, service_id: undefined, page: undefined })}>
              Reset
            </Button>
          </div>
        </form>
      </Card>

      <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <h3 className="text-base font-semibold text-slate-700">Recorded gatherings</h3>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            disabled={!gatherings.length}
            onClick={() => {
              void handleBulkExport('csv');
            }}
          >
            Download CSV archive
          </Button>
          <Button
            type="button"
            variant="ghost"
            disabled={!gatherings.length}
            onClick={() => {
              void handleBulkExport('pdf');
            }}
          >
            Download PDFs
          </Button>
        </div>
      </div>

      <div className="space-y-3">
        {isLoading && <p className="text-slate-500">Loading gatherings…</p>}
        {!isLoading && gatherings.length === 0 && (
          <p className="text-slate-500">No gatherings found for the selected filters.</p>
        )}

        {gatherings.map((gathering) => (
          <Card key={gathering.uuid} className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-slate-900">{gathering.name}</h3>
              <p className="text-sm text-slate-500">
                {gathering.service?.name ? `${gathering.service.name} • ` : ''}
                {gathering.starts_at ? new Date(gathering.starts_at).toLocaleString() : 'Date TBD'}
              </p>
              {gathering.location && <p className="text-sm text-slate-500">{gathering.location}</p>}
            </div>
            <div className="flex flex-col items-start gap-2 md:flex-row md:items-center">
              <GatheringAttendanceSummary attendance={gathering.attendance} />
              <Link href={`/attendance/gatherings/${gathering.uuid}`}>
                <Button variant="secondary">Open log</Button>
              </Link>
            </div>
          </Card>
        ))}
      </div>

      {lastPage > 1 && (
        <div className="flex items-center justify-between text-sm text-slate-600">
          <span>
            Page {currentPage} of {lastPage}
          </span>
          <div className="flex items-center gap-2">
            <Button type="button" variant="secondary" size="sm" disabled={currentPage <= 1} onClick={() => handlePageChange(currentPage - 1)}>
              Previous
            </Button>
            <Button type="button" variant="secondary" size="sm" disabled={currentPage >= lastPage} onClick={() => handlePageChange(currentPage + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </section>
  );
}

export default function AttendancePage() {
  return (
    <Suspense fallback={<p className="text-slate-500">Loading attendance…</p>}>
      <AttendanceContent />
    </Suspense>
  );
}

function GatheringAttendanceSummary({ attendance }: { attendance: GatheringSummary['attendance'] }) {
  if (!attendance) {
    return <p className="text-sm text-slate-500">No attendance recorded yet.</p>;
  }

  return (
    <div className="grid grid-cols-3 gap-2 text-xs text-slate-600">
      <div className="rounded-md border border-slate-200 px-3 py-2 text-center">
        <p className="font-semibold text-slate-900">{attendance.present}</p>
        <p>Present</p>
      </div>
      <div className="rounded-md border border-slate-200 px-3 py-2 text-center">
        <p className="font-semibold text-slate-900">{attendance.absent}</p>
        <p>Absent</p>
      </div>
      <div className="rounded-md border border-slate-200 px-3 py-2 text-center">
        <p className="font-semibold text-slate-900">{attendance.excused}</p>
        <p>Excused</p>
      </div>
    </div>
  );
}

function AttendanceAnalyticsSection({
  data,
  isLoading,
  onExport,
}: {
  data: AttendanceAnalytics | null | undefined;
  isLoading: boolean;
  onExport?: () => void | Promise<void>;
}) {
  if (isLoading) {
    return (
      <Card className="text-sm text-slate-500">
        Loading attendance insights…
      </Card>
    );
  }

  if (!data) {
    return null;
  }

  const summary = data.summary ?? {
    gatherings_tracked: 0,
    total_check_ins: 0,
    attendance_rate: 0,
    average_present: 0,
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 className="text-lg font-semibold text-slate-900">Attendance insights</h3>
          <p className="text-xs text-slate-500">Rolling eight-week snapshot of gatherings and follow-ups.</p>
        </div>
        {onExport ? (
          <Button
            type="button"
            variant="secondary"
            onClick={() => {
              void onExport();
            }}
          >
            Export CSV
          </Button>
        ) : null}
      </div>

      <div className="grid gap-4 md:grid-cols-4">
        <StatCard title="Gatherings tracked" value={summary.gatherings_tracked ?? '—'} />
        <StatCard title="Total check-ins" value={summary.total_check_ins ?? '—'} />
        <StatCard
          title="Attendance rate"
          value={summary.attendance_rate !== undefined ? `${summary.attendance_rate}%` : '—'}
          helperText="Present vs. recorded attendees"
        />
        <StatCard
          title="Average present"
          value={summary.average_present ?? '—'}
          helperText="Per gathering"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Weekly attendance trend</h3>
            <p className="text-xs text-slate-500">Past eight weeks</p>
          </header>
          {data.trend.length ? (
            <AttendanceTrend data={data.trend} />
          ) : (
            <p className="text-sm text-slate-500">Attendance trend data will appear as gatherings are recorded.</p>
          )}
        </Card>

        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Top gatherings</h3>
            <p className="text-xs text-slate-500">By present attendees</p>
          </header>
          {data.top_gatherings.length ? (
            <TopGatheringsTable gatherings={data.top_gatherings} />
          ) : (
            <p className="text-sm text-slate-500">Record attendance to see high-performing gatherings.</p>
          )}
        </Card>
      </div>

      <Card className="space-y-4">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Follow-up suggestions</h3>
          <p className="text-xs text-slate-500">Members with repeated absences</p>
        </header>
        {data.followup_candidates.length ? (
          <FollowupCandidatesList candidates={data.followup_candidates} />
        ) : (
          <p className="text-sm text-slate-500">Great job! No repeated absences were detected in the recent window.</p>
        )}
      </Card>
    </div>
  );
}

function AttendanceTrend({ data }: { data: AttendanceAnalytics['trend'] }) {
  const maxTotal = Math.max(
    ...data.map((item) => item.present + item.absent + item.excused),
    1
  );

  return (
    <ul className="space-y-3">
      {data.map((item) => {
        const total = item.present + item.absent + item.excused;
        const presentPercent = total > 0 ? Math.round((item.present / total) * 100) : 0;
        const absentPercent = total > 0 ? Math.round((item.absent / total) * 100) : 0;
        const excusedPercent = Math.max(0, 100 - presentPercent - absentPercent);

        return (
          <li key={item.label} className="space-y-1 rounded-lg border border-slate-200 p-3">
            <div className="flex items-center justify-between text-xs text-slate-500">
              <span>{item.label}</span>
              <span>{total} attendees</span>
            </div>
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-slate-200">
              <div className="bg-emerald-500" style={{ width: `${presentPercent}%` }} />
              <div className="bg-amber-400" style={{ width: `${absentPercent}%` }} />
              <div className="bg-slate-400" style={{ width: `${excusedPercent}%` }} />
            </div>
            <div className="flex items-center gap-4 text-xs text-slate-600">
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                Present {item.present}
              </span>
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-amber-400" />
                Absent {item.absent}
              </span>
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-slate-400" />
                Excused {item.excused}
              </span>
            </div>
          </li>
        );
      })}
    </ul>
  );
}

function TopGatheringsTable({ gatherings }: { gatherings: AttendanceAnalytics['top_gatherings'] }) {
  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Gathering</TableHeaderCell>
            <TableHeaderCell>Service</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Attendance</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {gatherings.map((gathering) => (
            <TableRow key={gathering.id}>
              <TableCell>
                <div className="flex flex-col">
                  <span className="font-medium text-slate-900">{gathering.name}</span>
                  <span className="text-xs text-slate-500">{formatDateTime(gathering.starts_at)}</span>
                </div>
              </TableCell>
              <TableCell className="text-sm text-slate-500">{gathering.service ?? '—'}</TableCell>
              <TableCell className="text-right font-medium text-slate-900">{gathering.present}</TableCell>
              <TableCell className="text-right text-sm text-slate-500">{gathering.attendance_rate}%</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function FollowupCandidatesList({ candidates }: { candidates: AttendanceAnalytics['followup_candidates'] }) {
  return (
    <ul className="grid gap-3 md:grid-cols-2">
      {candidates.map((candidate) => {
        const attendanceRate =
          candidate.total > 0 ? Math.round((candidate.present / candidate.total) * 100) : 0;

        return (
          <li key={candidate.member_id} className="rounded-lg border border-slate-200 p-4 shadow-sm">
            <p className="font-medium text-slate-900">{candidate.member_name}</p>
            <p className="text-xs text-slate-500">Absent {candidate.absent} of {candidate.total} tracked gatherings</p>
            <div className="mt-3 grid grid-cols-3 gap-2 text-xs text-slate-600">
              <div className="rounded-md border border-slate-200 px-2 py-1 text-center">
                <p className="font-semibold text-slate-900">{candidate.absent}</p>
                <p>Absent</p>
              </div>
              <div className="rounded-md border border-slate-200 px-2 py-1 text-center">
                <p className="font-semibold text-slate-900">{candidate.present}</p>
                <p>Present</p>
              </div>
              <div className="rounded-md border border-slate-200 px-2 py-1 text-center">
                <p className="font-semibold text-slate-900">{attendanceRate}%</p>
                <p>Attendance</p>
              </div>
            </div>
          </li>
        );
      })}
    </ul>
  );
}

function formatDateTime(value?: string | null): string {
  if (!value) {
    return 'Date TBD';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return 'Date TBD';
  }
  return date.toLocaleString();
}
