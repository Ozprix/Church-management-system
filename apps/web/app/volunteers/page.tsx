'use client';

import Link from 'next/link';
import { FormEvent, Suspense, useCallback, useMemo, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import {
  Button,
  Card,
  Input,
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
import { useMembers } from '@/hooks/use-members';
import {
  useVolunteerAssignments,
  useVolunteerRoles,
  useVolunteerTeams,
  useCreateVolunteerAssignment,
  useSwapVolunteerAssignment,
  useVolunteerAvailability,
  useUpsertVolunteerAvailability,
} from '@/hooks/use-volunteers';

function toIsoLocal(value: string | null) {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function VolunteersContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const { pushToast } = useToast();

  const page = Number(searchParams.get('page') ?? '1');
  const status = searchParams.get('status') ?? '';

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

  const { data: assignmentResponse, isLoading: assignmentsLoading } = useVolunteerAssignments({
    status: status || undefined,
    page,
    per_page: 10,
  });
  const assignments = useMemo(() => assignmentResponse?.data ?? [], [assignmentResponse?.data]);
  const assignmentMeta = assignmentResponse?.meta;

  const { data: rolesResponse } = useVolunteerRoles({ per_page: 100 });
  const roles = rolesResponse?.data ?? [];
  const { data: teamsResponse } = useVolunteerTeams({ per_page: 100 });
  const teams = teamsResponse?.data ?? [];
  const { data: membersResponse } = useMembers({ per_page: 100 });
  const members = membersResponse?.data ?? [];

  const { data: availabilityResponse } = useVolunteerAvailability({ per_page: 50 });
  const availabilityList = availabilityResponse?.data ?? [];

  const createAssignment = useCreateVolunteerAssignment();
  const swapAssignment = useSwapVolunteerAssignment();
  const updateAvailability = useUpsertVolunteerAvailability();

  const [swapSource, setSwapSource] = useState<number | ''>('');
  const [swapTarget, setSwapTarget] = useState<number | ''>('');

  const handleAssignmentSubmit = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      const memberId = Number(formData.get('member_id'));
      const roleId = Number(formData.get('volunteer_role_id'));
      const teamId = formData.get('volunteer_team_id');
      const startsAtRaw = formData.get('starts_at') as string;
      const endsAtRaw = formData.get('ends_at') as string;

      if (!memberId || !roleId || !startsAtRaw) {
        pushToast({ title: 'Member, role, and start time are required.', variant: 'error' });
        return;
      }

      const startsAt = toIsoLocal(startsAtRaw);
      const endsAt = toIsoLocal(endsAtRaw || null);

      try {
        await createAssignment.mutateAsync({
          member_id: memberId,
          volunteer_role_id: roleId,
          volunteer_team_id: teamId ? Number(teamId) : undefined,
          starts_at: startsAt ?? new Date().toISOString(),
          ends_at: endsAt ?? undefined,
        });
        pushToast({ title: 'Assignment scheduled', variant: 'success' });
        event.currentTarget.reset();
      } catch (error) {
        pushToast({
          title: 'Unable to schedule assignment',
          description: error instanceof Error ? error.message : 'Unknown error',
          variant: 'error',
        });
      }
    },
    [createAssignment, pushToast]
  );

  const handleSwapSubmit = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      if (!swapSource || !swapTarget) {
        pushToast({ title: 'Select both assignments to swap.', variant: 'error' });
        return;
      }

      try {
        await swapAssignment.mutateAsync({ sourceId: Number(swapSource), targetId: Number(swapTarget) });
        pushToast({ title: 'Assignments swapped', variant: 'success' });
        setSwapSource('');
        setSwapTarget('');
      } catch (error) {
        pushToast({
          title: 'Unable to swap assignments',
          description: error instanceof Error ? error.message : 'Unknown error',
          variant: 'error',
        });
      }
    },
    [swapAssignment, swapSource, swapTarget, pushToast]
  );

  const handleAvailabilitySubmit = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      const memberId = Number(formData.get('availability_member_id'));
      if (!memberId) {
        pushToast({ title: 'Select a member to update availability.', variant: 'error' });
        return;
      }

      const weekdayValues = Array.from(formData.getAll('weekdays')) as string[];
      const payload = {
        member_id: memberId,
        weekdays: weekdayValues,
        time_blocks: [
          {
            start: (formData.get('time_start') as string) || '08:00',
            end: (formData.get('time_end') as string) || '12:00',
          },
        ],
        notes: (formData.get('availability_notes') as string) || undefined,
      };

      try {
        await updateAvailability.mutateAsync(payload);
        pushToast({ title: 'Availability saved', variant: 'success' });
      } catch (error) {
        pushToast({
          title: 'Unable to save availability',
          description: error instanceof Error ? error.message : 'Unknown error',
          variant: 'error',
        });
      }
    },
    [updateAvailability, pushToast]
  );

  const currentPage = assignmentMeta?.current_page ?? 1;
  const lastPage = assignmentMeta?.last_page ?? 1;

  const assignmentOptions = useMemo(
    () =>
      assignments.map((assignment) => ({
        id: assignment.id,
        label: `${assignment.member?.first_name ?? 'Member'} - ${assignment.role?.name ?? 'Role'} (${assignment.starts_at ? new Date(assignment.starts_at).toLocaleString() : 'TBD'})`,
      })),
    [assignments]
  );

  return (
    <section className="space-y-6">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Volunteer Scheduling</h2>
          <p className="text-sm text-slate-500">Coordinate teams, roles, and service assignments.</p>
        </div>
        <Link href="/communication" className="text-sm text-emerald-600 hover:text-emerald-700">
          Communication center
        </Link>
      </header>

      <Card className="space-y-4">
        <h3 className="text-lg font-semibold text-slate-900">Schedule assignment</h3>
        <form className="grid gap-4 md:grid-cols-3" onSubmit={handleAssignmentSubmit}>
          <div>
            <Label htmlFor="member_id" required>
              Member
            </Label>
            <Select id="member_id" name="member_id" defaultValue="">
              <option value="">Select member…</option>
              {members.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.first_name} {member.last_name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="volunteer_role_id" required>
              Role
            </Label>
            <Select id="volunteer_role_id" name="volunteer_role_id" defaultValue="">
              <option value="">Select role…</option>
              {roles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="volunteer_team_id">Team</Label>
            <Select id="volunteer_team_id" name="volunteer_team_id" defaultValue="">
              <option value="">No specific team</option>
              {teams.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="starts_at" required>
              Starts
            </Label>
            <Input id="starts_at" name="starts_at" type="datetime-local" required />
          </div>
          <div>
            <Label htmlFor="ends_at">Ends</Label>
            <Input id="ends_at" name="ends_at" type="datetime-local" />
          </div>
          <div className="md:col-span-3 flex justify-end">
            <Button type="submit" loading={createAssignment.isPending}>
              {createAssignment.isPending ? 'Scheduling…' : 'Schedule assignment'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <header className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Swap assignments</h3>
          <p className="text-sm text-slate-500">Swap responsibilities between two scheduled volunteers.</p>
        </header>
        <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSwapSubmit}>
          <div>
            <Label htmlFor="swap-source" required>
              Source assignment
            </Label>
            <Select
              id="swap-source"
              value={swapSource}
              onChange={(event) => setSwapSource(event.target.value === '' ? '' : Number(event.target.value))}
            >
              <option value="">Select assignment…</option>
              {assignmentOptions.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="swap-target" required>
              Target assignment
            </Label>
            <Select
              id="swap-target"
              value={swapTarget}
              onChange={(event) => setSwapTarget(event.target.value === '' ? '' : Number(event.target.value))}
            >
              <option value="">Select assignment…</option>
              {assignmentOptions.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div className="md:col-span-2 flex justify-end">
            <Button type="submit" variant="secondary" loading={swapAssignment.isPending}>
              {swapAssignment.isPending ? 'Swapping…' : 'Swap assignments'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <header className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Upcoming roster</h3>
          <form
            className="flex flex-wrap items-center gap-2"
            onSubmit={(event) => {
              event.preventDefault();
              const formData = new FormData(event.currentTarget);
              updateParams({ status: (formData.get('filter-status') as string) ?? undefined, page: '1' });
            }}
          >
            <Label htmlFor="filter-status" className="!mb-0 text-sm text-slate-600">
              Status
            </Label>
            <Select id="filter-status" name="filter-status" defaultValue={status}>
              <option value="">All</option>
              <option value="scheduled">Scheduled</option>
              <option value="confirmed">Confirmed</option>
              <option value="swapped">Swapped</option>
              <option value="cancelled">Cancelled</option>
            </Select>
            <Button type="submit" size="sm">
              Apply
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => updateParams({ status: undefined, page: undefined })}>
              Reset
            </Button>
          </form>
        </header>

        {assignmentsLoading ? (
          <p className="text-slate-500">Loading assignments…</p>
        ) : assignments.length === 0 ? (
          <p className="text-slate-500">No volunteer assignments scheduled yet.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Volunteer</TableHeaderCell>
                  <TableHeaderCell>Role</TableHeaderCell>
                  <TableHeaderCell>Team</TableHeaderCell>
                  <TableHeaderCell>Starts</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {assignments.map((assignment) => (
                  <TableRow key={assignment.id}>
                    <TableCell className="font-medium text-slate-900">
                      {assignment.member ? `${assignment.member.first_name} ${assignment.member.last_name}` : 'Unassigned'}
                    </TableCell>
                    <TableCell>{assignment.role?.name ?? '—'}</TableCell>
                    <TableCell>{assignment.team?.name ?? '—'}</TableCell>
                    <TableCell>{assignment.starts_at ? new Date(assignment.starts_at).toLocaleString() : '—'}</TableCell>
                    <TableCell className="capitalize">{assignment.status}</TableCell>
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
              <Button type="button" variant="secondary" size="sm" disabled={currentPage <= 1} onClick={() => updateParams({ page: String(currentPage - 1) })}>
                Previous
              </Button>
              <Button type="button" variant="secondary" size="sm" disabled={currentPage >= lastPage} onClick={() => updateParams({ page: String(currentPage + 1) })}>
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>

      <Card className="space-y-4">
        <h3 className="text-lg font-semibold text-slate-900">Availability</h3>
        <form className="grid gap-4 md:grid-cols-4" onSubmit={handleAvailabilitySubmit}>
          <div>
            <Label htmlFor="availability_member_id" required>
              Member
            </Label>
            <Select id="availability_member_id" name="availability_member_id" defaultValue="">
              <option value="">Select member…</option>
              {members.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.first_name} {member.last_name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="weekdays" required>
              Preferred days
            </Label>
            <select
              id="weekdays"
              name="weekdays"
              multiple
              className="block h-24 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm"
            >
              {['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'].map((day) => (
                <option key={day} value={day}>
                  {day.charAt(0).toUpperCase() + day.slice(1)}
                </option>
              ))}
            </select>
          </div>
          <div>
            <Label htmlFor="time_start">Preferred start</Label>
            <Input id="time_start" name="time_start" type="time" defaultValue="08:00" />
          </div>
          <div>
            <Label htmlFor="time_end">Preferred end</Label>
            <Input id="time_end" name="time_end" type="time" defaultValue="12:00" />
          </div>
          <div className="md:col-span-4">
            <Label htmlFor="availability_notes">Notes</Label>
            <textarea
              id="availability_notes"
              name="availability_notes"
              rows={3}
              className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              placeholder="Any constraints or preferences"
            />
          </div>
          <div className="md:col-span-4 flex justify-end">
            <Button type="submit" loading={updateAvailability.isPending}>
              {updateAvailability.isPending ? 'Saving…' : 'Save availability'}
            </Button>
          </div>
        </form>

        {availabilityList.length > 0 && (
          <div className="space-y-2 text-sm text-slate-600">
            <h4 className="font-semibold text-slate-700">Recent updates</h4>
            {availabilityList.map((item) => (
              <div key={item.id} className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                <p className="font-medium text-slate-900">
                  {item.member ? `${item.member.first_name} ${item.member.last_name}` : 'Member'}
                </p>
                <p>Weekdays: {item.weekdays?.join(', ') ?? 'Not specified'}</p>
                <p>
                  Time blocks:{' '}
                  {item.time_blocks?.map((block) => `${block.start}–${block.end}`).join(', ') ?? 'Flexible'}
                </p>
                {item.notes && <p>Notes: {item.notes}</p>}
              </div>
            ))}
          </div>
        )}
      </Card>
    </section>
  );
}

export default function VolunteersPage() {
  return (
    <Suspense fallback={<p className="text-slate-500">Loading volunteers…</p>}>
      <VolunteersContent />
    </Suspense>
  );
}
