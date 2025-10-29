'use client';

import { FormEvent, useMemo, useState } from 'react';
import clsx from 'clsx';
import Link from 'next/link';
import {
  Badge,
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
  Textarea,
  useToast,
} from '@church/ui';
import {
  VOLUNTEER_SIGNUP_STAGES,
  type VolunteerSignup,
  type VolunteerSignupStage,
} from '@/lib/api/volunteers';
import { useDeleteVolunteerSignup, useUpdateVolunteerSignup, useVolunteerSignups } from '@/hooks/use-volunteers';
import { useTenantId } from '@/lib/tenant';

function toLocalDateTime(value?: string | null) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (num: number) => num.toString().padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatStageHistory(history: { label: string; changed_at?: string; notes?: string | null }[] | null | undefined) {
  if (!history || history.length === 0) return [];
  return [...history]
    .slice(-3)
    .reverse()
    .map((entry) => ({
      label: entry.label,
      changedAt: entry.changed_at ? new Date(entry.changed_at).toLocaleString() : '—',
      notes: entry.notes ?? null,
    }));
}

export default function VolunteerPipelinePage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [stageFilter, setStageFilter] = useState<'all' | VolunteerSignupStage>('all');
  const { data: signupResponse, isLoading } = useVolunteerSignups({
    stage: stageFilter === 'all' ? undefined : stageFilter,
    per_page: 50,
  });
  const signups = useMemo(
    () => (signupResponse?.data ?? []) as VolunteerSignup[],
    [signupResponse?.data]
  );

  const updateSignup = useUpdateVolunteerSignup();
  const deleteSignup = useDeleteVolunteerSignup();
  const stageSummary = useMemo(
    () =>
      VOLUNTEER_SIGNUP_STAGES.map((stage) => ({
        ...stage,
        count: signups.filter((signup) => signup.stage === stage.value).length,
      })),
    [signups]
  );

  const handleStageForm = async (event: FormEvent<HTMLFormElement>, signupId: number) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const nextStage = (formData.get('stage') as VolunteerSignupStage) ?? undefined;
    const followUpAt = (formData.get('follow_up_at') as string) || undefined;
    const notes = (formData.get('stage_notes') as string) || undefined;

    try {
      await updateSignup.mutateAsync({
        id: signupId,
        payload: {
          stage: nextStage,
          follow_up_at: followUpAt ? new Date(followUpAt).toISOString() : null,
          stage_notes: notes,
        },
      });
      pushToast({ title: 'Signup updated', variant: 'success' });
    } catch (error) {
      pushToast({
        title: 'Unable to update signup',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'error',
      });
    }
  };

  const handleMarkContacted = async (signupId: number) => {
    try {
      await updateSignup.mutateAsync({
        id: signupId,
        payload: {
          last_contacted_at: new Date().toISOString(),
        },
      });
      pushToast({ title: 'Contacted timestamp recorded', variant: 'success' });
    } catch (error) {
      pushToast({
        title: 'Unable to record contact',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'error',
      });
    }
  };

  const handleQuickFollowUp = async (signupId: number, days: number) => {
    const target = new Date();
    target.setDate(target.getDate() + days);

    try {
      await updateSignup.mutateAsync({
        id: signupId,
        payload: {
          follow_up_at: target.toISOString(),
        },
      });
      pushToast({ title: `Follow-up scheduled in ${days} day${days === 1 ? '' : 's'}`, variant: 'success' });
    } catch (error) {
      pushToast({
        title: 'Unable to schedule follow-up',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'error',
      });
    }
  };

  const handleArchive = async (signupId: number) => {
    try {
      await updateSignup.mutateAsync({
        id: signupId,
        payload: {
          stage: 'inactive',
          stage_notes: 'Marked inactive',
        },
      });
      pushToast({ title: 'Signup archived', variant: 'success' });
    } catch (error) {
      pushToast({
        title: 'Unable to archive signup',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'error',
      });
    }
  };

  const handleDelete = async (signupId: number) => {
    try {
      await deleteSignup.mutateAsync(signupId);
      pushToast({ title: 'Signup deleted', variant: 'success' });
    } catch (error) {
      pushToast({
        title: 'Unable to delete signup',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'error',
      });
    }
  };

  const stageOptions = useMemo(() => [{ value: 'all', label: 'All stages' }, ...VOLUNTEER_SIGNUP_STAGES], []);

  return (
    <section className="space-y-6">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Volunteer pipeline</h2>
          <p className="text-sm text-slate-500">Review applicants, track onboarding, and stay ahead on follow-ups.</p>
        </div>
        <Link href="/volunteers" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to volunteer scheduling
        </Link>
      </header>

      <Card padding="md" className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div className="flex flex-col gap-2">
          <Label htmlFor="stage-filter">Stage</Label>
          <Select
            id="stage-filter"
            value={stageFilter}
            onChange={(event) => setStageFilter(event.target.value as typeof stageFilter)}
            className="md:min-w-[220px]"
          >
            {stageOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </div>
        <div className="text-xs text-slate-500 md:text-right">
          {tenantId ? `Tenant ID: ${tenantId}` : 'No tenant selected'}
        </div>
      </Card>

      <Card padding="md" className="grid gap-3 md:grid-cols-3">
        {stageSummary.map((summary) => (
          <div key={summary.value} className="space-y-1">
            <p className="text-xs uppercase tracking-wide text-slate-500">{summary.label}</p>
            <p className="text-xl font-semibold text-slate-900">{summary.count}</p>
          </div>
        ))}
      </Card>

      {isLoading ? (
        <Card padding="md">Loading volunteer pipeline…</Card>
      ) : signups.length === 0 ? (
        <Card padding="md">No volunteer signups found for this stage.</Card>
      ) : (
        <div className="space-y-4">
          {signups.map((signup) => {
            const history = formatStageHistory(signup.stage_history ?? null);
            const memberName = signup.member
              ? `${signup.member.first_name} ${signup.member.last_name}`
              : signup.name ?? 'Unknown applicant';
            const followUpDate = signup.follow_up_at ? new Date(signup.follow_up_at) : null;
            const followUpDue = followUpDate ? followUpDate.getTime() <= Date.now() : false;

            return (
              <Card
                key={signup.id}
                padding="lg"
                className={clsx('space-y-4 border border-slate-200 shadow-sm', followUpDue && 'border-amber-400 shadow-amber-200')}
              >
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <h3 className="text-lg font-semibold text-slate-900">{memberName}</h3>
                      <Badge>{signup.stage_label}</Badge>
                    </div>
                    <p className="text-sm text-slate-500">
                      {signup.role?.name ? `Role preference: ${signup.role.name}` : 'No role selected yet'}
                    </p>
                    <div className="text-xs text-slate-500 space-x-3">
                      {signup.email ? <span>Email: {signup.email}</span> : null}
                      {signup.phone ? <span>Phone: {signup.phone}</span> : null}
                      {followUpDate ? (
                        <span className={clsx(followUpDue ? 'text-amber-600 font-medium' : undefined)}>
                          Follow-up: {followUpDate.toLocaleString()}
                        </span>
                      ) : (
                        <span>Follow-up: not scheduled</span>
                      )}
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button type="button" variant="ghost" size="sm" onClick={() => handleMarkContacted(signup.id)}>
                      Mark contacted
                    </Button>
                    <Button type="button" variant="ghost" size="sm" onClick={() => handleArchive(signup.id)}>
                      Archive
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-rose-600"
                      onClick={() => handleDelete(signup.id)}
                    >
                      Delete
                    </Button>
                  </div>
                </div>

                <form className="grid gap-4 md:grid-cols-4" onSubmit={(event) => handleStageForm(event, signup.id)}>
                  <div>
                    <Label htmlFor={`stage-${signup.id}`}>Stage</Label>
                    <Select id={`stage-${signup.id}`} name="stage" defaultValue={signup.stage}>
                      {VOLUNTEER_SIGNUP_STAGES.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor={`follow-up-${signup.id}`}>Follow-up</Label>
                    <Input
                      id={`follow-up-${signup.id}`}
                      name="follow_up_at"
                      type="datetime-local"
                      defaultValue={toLocalDateTime(signup.follow_up_at)}
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label htmlFor={`notes-${signup.id}`}>Notes</Label>
                    <Textarea
                      id={`notes-${signup.id}`}
                      name="stage_notes"
                      rows={2}
                      placeholder="Add coordinator notes…"
                    />
                  </div>
                  <div className="md:col-span-4 flex flex-wrap items-center justify-end gap-2">
                    <span className="mr-auto text-xs text-slate-500">Quick follow-up</span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => handleQuickFollowUp(signup.id, 3)}>
                      in 3 days
                    </Button>
                    <Button type="button" variant="ghost" size="sm" onClick={() => handleQuickFollowUp(signup.id, 7)}>
                      next week
                    </Button>
                    <Button type="submit" loading={updateSignup.isPending}>
                      {updateSignup.isPending ? 'Updating…' : 'Save changes'}
                    </Button>
                  </div>
                </form>

                {history.length > 0 ? (
                  <TableContainer>
                    <Table>
                      <TableHead>
                        <TableRow>
                          <TableHeaderCell>Recent transitions</TableHeaderCell>
                          <TableHeaderCell>When</TableHeaderCell>
                          <TableHeaderCell>Notes</TableHeaderCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {history.map((entry, index) => (
                          <TableRow key={`${signup.id}-history-${index}`}>
                            <TableCell>{entry.label}</TableCell>
                            <TableCell>{entry.changedAt}</TableCell>
                            <TableCell>{entry.notes ?? '—'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                ) : (
                  <p className="text-xs text-slate-500">No recorded transitions yet.</p>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </section>
  );
}
