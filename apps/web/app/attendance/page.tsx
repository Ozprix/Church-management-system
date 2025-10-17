'use client';

import Link from 'next/link';
import { FormEvent, Suspense, useCallback, useMemo } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Button, Card, Label, Select } from '@church/ui';
import { useGatherings } from '@/hooks/use-gatherings';
import { useServices } from '@/hooks/use-services';
import type { GatheringSummary } from '@/lib/api/gatherings';

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
              <AttendanceSummary attendance={gathering.attendance} />
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

function AttendanceSummary({ attendance }: { attendance: GatheringSummary['attendance'] }) {
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
