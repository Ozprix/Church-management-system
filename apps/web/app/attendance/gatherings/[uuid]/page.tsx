'use client';

import Link from 'next/link';
import { useParams, usePathname, useRouter, useSearchParams } from 'next/navigation';
import { FormEvent, Suspense, useCallback } from 'react';
import {
  Button,
  Card,
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
import { useGathering } from '@/hooks/use-gathering';
import { useAttendance, useRecordAttendance, useUpdateAttendance } from '@/hooks/use-attendance';
import { useMembers } from '@/hooks/use-members';

const ATTENDANCE_STATUS_OPTIONS = [
  { value: 'present', label: 'Present' },
  { value: 'absent', label: 'Absent' },
  { value: 'excused', label: 'Excused' },
] as const;

function GatheringDetailContent() {
  const { uuid } = useParams<{ uuid: string }>();
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const { pushToast } = useToast();

  const statusFilter = searchParams.get('status') ?? '';
  const page = Number(searchParams.get('page') ?? '1');

  const { data: gathering, isLoading } = useGathering(uuid);
  const { data: attendanceResponse, isLoading: isAttendanceLoading } = useAttendance(uuid, {
    status: statusFilter || undefined,
    page,
    per_page: 25,
  });
  const attendanceRecords = attendanceResponse?.data ?? [];
  const meta = attendanceResponse?.meta;

  const { data: membersResponse } = useMembers({ per_page: 100 });
  const members = membersResponse?.data ?? [];

  const recordMutation = useRecordAttendance(uuid);
  const updateMutation = useUpdateAttendance(uuid);

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
      router.push(`${pathname.split('?')[0]}${query ? `?${query}` : ''}`);
    },
    [pathname, router, searchParams]
  );

  const handleRecord = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      const memberId = Number(formData.get('member_id'));
      const status = String(formData.get('status') ?? 'present') as 'present' | 'absent' | 'excused';

      if (!memberId) {
        pushToast({ title: 'Select a member', variant: 'error' });
        return;
      }

      try {
        await recordMutation.mutateAsync({
          member_id: memberId,
          status,
          check_in_method: 'manual',
        });
        pushToast({ title: 'Attendance recorded', variant: 'success' });
        event.currentTarget.reset();
      } catch (error) {
        pushToast({
          title: 'Unable to record attendance',
          description: error instanceof Error ? error.message : 'Unknown error',
          variant: 'error',
        });
      }
    },
    [pushToast, recordMutation]
  );

  const handleStatusFilter = useCallback(
    (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      updateParams({ status: (formData.get('status') as string) ?? undefined, page: '1' });
    },
    [updateParams]
  );

  const handlePageChange = useCallback(
    (nextPage: number) => {
      updateParams({ page: String(nextPage) });
    },
    [updateParams]
  );

  if (isLoading) {
    return <p className="text-slate-500">Loading gathering…</p>;
  }

  if (!gathering) {
    return (
      <div className="space-y-4">
        <p className="text-slate-500">Gathering not found.</p>
        <Link href="/attendance" className="text-emerald-600 hover:text-emerald-700">
          Back to attendance
        </Link>
      </div>
    );
  }

  const currentPage = meta?.current_page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  return (
    <section className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">{gathering.name}</h2>
          <p className="text-sm text-slate-500">
            {gathering.starts_at ? new Date(gathering.starts_at).toLocaleString() : 'Date TBD'}
            {gathering.location ? ` • ${gathering.location}` : ''}
          </p>
          {gathering.service?.name && <p className="text-sm text-slate-500">Service: {gathering.service.name}</p>}
        </div>
        <Link href="/attendance" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to attendance
        </Link>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className="space-y-3">
          <h3 className="text-sm font-semibold text-slate-700">Attendance summary</h3>
          {gathering.attendance ? (
            <div className="grid grid-cols-3 gap-2 text-xs text-slate-600">
              <SummaryTile label="Present" value={gathering.attendance.present} />
              <SummaryTile label="Absent" value={gathering.attendance.absent} />
              <SummaryTile label="Excused" value={gathering.attendance.excused} />
            </div>
          ) : (
            <p className="text-sm text-slate-500">No attendance recorded yet.</p>
          )}
        </Card>
        <Card className="md:col-span-2">
          <form className="grid gap-4 md:grid-cols-3" onSubmit={handleRecord}>
            <div className="md:col-span-2">
              <Label htmlFor="member-select" required>
                Member
              </Label>
              <Select id="member-select" name="member_id" defaultValue="">
                <option value="">Select a member…</option>
                {members.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.first_name} {member.last_name}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label htmlFor="attendance-status">Status</Label>
              <Select id="attendance-status" name="status" defaultValue="present">
                {ATTENDANCE_STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </div>
            <div className="md:col-span-3 flex justify-end">
              <Button type="submit" loading={recordMutation.isPending}>
                {recordMutation.isPending ? 'Saving…' : 'Record attendance'}
              </Button>
            </div>
          </form>
        </Card>
      </div>

      <Card className="space-y-4">
        <header className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Attendance log</h3>
          <form className="flex items-center gap-2" onSubmit={handleStatusFilter}>
            <Label htmlFor="filter-status" className="!mb-0 text-sm text-slate-600">
              Filter
            </Label>
            <Select id="filter-status" name="status" defaultValue={statusFilter}>
              <option value="">All statuses</option>
              {ATTENDANCE_STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
            <Button type="submit" size="sm">
              Apply
            </Button>
          </form>
        </header>

        {isAttendanceLoading ? (
          <p className="text-slate-500">Loading attendance…</p>
        ) : attendanceRecords.length === 0 ? (
          <p className="text-slate-500">No attendance records yet.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Member</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Checked in</TableHeaderCell>
                  <TableHeaderCell>Method</TableHeaderCell>
                  <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {attendanceRecords.map((record) => (
                  <TableRow key={record.id}>
                    <TableCell className="font-medium text-slate-900">
                      {record.member ? `${record.member.first_name} ${record.member.last_name}` : 'Unknown member'}
                    </TableCell>
                    <TableCell className="capitalize">{record.status}</TableCell>
                    <TableCell>
                      {record.checked_in_at ? new Date(record.checked_in_at).toLocaleString() : '—'}
                    </TableCell>
                    <TableCell>{record.check_in_method ?? '—'}</TableCell>
                    <TableCell className="text-right">
                      <div className="inline-flex flex-wrap items-center gap-2">
                        {ATTENDANCE_STATUS_OPTIONS.map((option) => (
                          <Button
                            key={option.value}
                            type="button"
                            size="sm"
                            variant={record.status === option.value ? 'primary' : 'secondary'}
                            disabled={updateMutation.isPending}
                            onClick={async () => {
                              try {
                                await updateMutation.mutateAsync({
                                  attendanceId: record.id,
                                  payload: { status: option.value },
                                });
                                pushToast({ title: `Marked ${option.label.toLowerCase()}`, variant: 'success' });
                              } catch (error) {
                                pushToast({
                                  title: 'Update failed',
                                  description: error instanceof Error ? error.message : 'Unknown error',
                                  variant: 'error',
                                });
                              }
                            }}
                          >
                            {option.label}
                          </Button>
                        ))}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}

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
      </Card>
    </section>
  );
}

export default function GatheringDetailPage() {
  return (
    <Suspense fallback={<p className="text-slate-500">Loading gathering…</p>}>
      <GatheringDetailContent />
    </Suspense>
  );
}

function SummaryTile({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-md border border-slate-200 px-3 py-2 text-center">
      <p className="text-base font-semibold text-slate-900">{value}</p>
      <p className="text-xs text-slate-500">{label}</p>
    </div>
  );
}
