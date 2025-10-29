'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import {
  Button,
  Card,
  Input,
  Label,
  Select,
  Badge,
  useToast,
  classNames,
} from '@church/ui';
import { useGatherings } from '@/hooks/use-gatherings';
import { useMembers } from '@/hooks/use-members';
import { useAttendance } from '@/hooks/use-attendance';
import { useTenantId } from '@/lib/tenant';
import { recordAttendance } from '@/lib/api/gatherings';
import { useAttendanceOfflineQueue } from '@/hooks/use-attendance-offline-queue';
import type { AttendancePayload, GatheringSummary } from '@/lib/api/gatherings';

function useOnlineStatus() {
  const [online, setOnline] = useState<boolean>(
    typeof navigator === 'undefined' ? true : navigator.onLine
  );

  useEffect(() => {
    const handleOnline = () => setOnline(true);
    const handleOffline = () => setOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return online;
}

export default function AttendanceKioskPage() {
  const { pushToast } = useToast();
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const isOnline = useOnlineStatus();

  const { data: gatheringsResponse } = useGatherings({
    status: 'scheduled',
    per_page: 50,
  });
  const allGatherings = useMemo(
    () => gatheringsResponse?.data ?? [],
    [gatheringsResponse?.data]
  );
  const [selectedGatheringUuid, setSelectedGatheringUuid] = useState<string | undefined>(undefined);

  useEffect(() => {
    if (!selectedGatheringUuid && allGatherings.length > 0) {
      setSelectedGatheringUuid(allGatherings[0].uuid);
    }
  }, [allGatherings, selectedGatheringUuid]);

  const selectedGathering = useMemo<GatheringSummary | undefined>(() => {
    return allGatherings.find((gathering) => gathering.uuid === selectedGatheringUuid);
  }, [allGatherings, selectedGatheringUuid]);

  const { data: membersResponse } = useMembers({
    per_page: 200,
    sort: 'first_name',
  });
  const members = useMemo(
    () => membersResponse?.data ?? [],
    [membersResponse?.data]
  );

  const { data: attendanceResponse } = useAttendance(selectedGatheringUuid, {
    status: 'present',
    per_page: 500,
  });
  const checkedInIds = useMemo(() => {
    const ids = new Set<number>();
    attendanceResponse?.data?.forEach((record) => {
      if (record.member?.id) {
        ids.add(record.member.id);
      }
    });
    return ids;
  }, [attendanceResponse?.data]);

  const {
    queue,
    enqueue,
    flush,
  } = useAttendanceOfflineQueue({
    tenantId: tenantId ?? undefined,
    gatheringUuid: selectedGatheringUuid,
    flushHandler: async (job) => {
      await recordAttendance(job.tenantId, job.gatheringUuid, job.payload);
    },
    onSynced: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['gathering'] });
    },
  });

  useEffect(() => {
    flush();
  }, [flush, selectedGatheringUuid]);

  const pendingMemberIds = useMemo(() => {
    return new Set<number>(queue.map((job) => job.member.id));
  }, [queue]);

  const [search, setSearch] = useState('');

  const filteredMembers = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) {
      return members;
    }
    return members.filter((member) => {
      const fullName = `${member.first_name} ${member.last_name}`.toLowerCase();
      return fullName.includes(term);
    });
  }, [members, search]);

  const handleCheckIn = async (memberId: number, name: string) => {
    if (!tenantId || !selectedGatheringUuid) {
      pushToast({ title: 'Select a gathering first', variant: 'error' });
      return;
    }

    const payload: AttendancePayload = {
      member_id: memberId,
      status: 'present',
      check_in_method: 'kiosk',
    };

    try {
      await recordAttendance(tenantId, selectedGatheringUuid, payload);
      pushToast({ title: `${name} checked in`, variant: 'success' });
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
    } catch (error) {
      enqueue({
        tenantId,
        gatheringUuid: selectedGatheringUuid,
        payload,
        member: {
          id: memberId,
          name,
        },
      });
      pushToast({
        title: 'Saved offline',
        description: 'Check-in will sync when connection restores.',
        variant: 'info',
      });
    }
  };

  const offlinePendingForCurrent = queue.filter(
    (job) =>
      (!tenantId || job.tenantId === tenantId) &&
      (!selectedGatheringUuid || job.gatheringUuid === selectedGatheringUuid)
  );

  return (
    <section className="space-y-6 pb-10">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-3xl font-semibold text-slate-900">Attendance kiosk</h2>
          <p className="text-sm text-slate-500">
            Quick check-in experience for volunteers or foyer tablets. Works offline and syncs when back online.
          </p>
        </div>
        <Link href="/attendance" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to attendance dashboard
        </Link>
      </header>

      <div className="flex flex-wrap items-center gap-3 text-sm">
        <Badge variant={isOnline ? 'success' : 'warning'}>
          {isOnline ? 'Online' : 'Offline mode'}
        </Badge>
        {offlinePendingForCurrent.length > 0 && (
          <Badge variant="warning">
            {offlinePendingForCurrent.length} pending sync
          </Badge>
        )}
      </div>

      <Card className="space-y-4">
        <div className="grid gap-4 md:grid-cols-3">
          <div className="md:col-span-2">
            <Label htmlFor="gathering-select">Select gathering</Label>
            <Select
              id="gathering-select"
              value={selectedGatheringUuid ?? ''}
              onChange={(event) => setSelectedGatheringUuid(event.target.value || undefined)}
            >
              {allGatherings.length === 0 && <option value="">No gatherings scheduled</option>}
              {allGatherings.map((gathering) => (
                <option key={gathering.uuid} value={gathering.uuid}>
                  {gathering.name} • {gathering.starts_at ? new Date(gathering.starts_at).toLocaleString() : 'Date TBD'}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="member-search">Search members</Label>
            <Input
              id="member-search"
              placeholder="Search by name"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
        </div>
        {selectedGathering && (
          <div className="rounded-md bg-slate-50 p-4 text-sm text-slate-600">
            <p>
              <span className="font-semibold text-slate-900">Location:</span>{' '}
              {selectedGathering.location ?? 'Not set'}
            </p>
            {selectedGathering.attendance && (
              <p className="mt-1">
                <span className="font-semibold text-slate-900">Present today:</span>{' '}
                {selectedGathering.attendance.present}
              </p>
            )}
          </div>
        )}
      </Card>

      <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        {filteredMembers.map((member) => {
          const fullName = `${member.first_name} ${member.last_name}`.trim();
          const isCheckedIn = checkedInIds.has(member.id);
          const isPending = pendingMemberIds.has(member.id);

          return (
            <Card
              key={member.id}
              className={classNames(
                'flex flex-col gap-3 border-2 transition',
                isCheckedIn
                  ? 'border-emerald-200 bg-emerald-50'
                  : isPending
                  ? 'border-amber-200 bg-amber-50'
                  : 'border-slate-200'
              )}
            >
              <div>
                <h3 className="text-lg font-semibold text-slate-900">{fullName}</h3>
                {member.membership_status && (
                  <p className="text-xs uppercase tracking-wide text-slate-500">{member.membership_status}</p>
                )}
              </div>
              <div className="mt-auto flex items-center justify-between gap-3">
                {isPending ? (
                  <Badge variant="warning">Queued</Badge>
                ) : isCheckedIn ? (
                  <Badge variant="success">Checked in</Badge>
                ) : (
                  <span />
                )}
                <Button
                  size="lg"
                  className="w-32"
                  variant={isCheckedIn ? 'secondary' : 'primary'}
                  disabled={isCheckedIn}
                  onClick={() => handleCheckIn(member.id, fullName)}
                >
                  {isCheckedIn ? 'Present' : 'Check in'}
                </Button>
              </div>
            </Card>
          );
        })}
        {filteredMembers.length === 0 && (
          <Card className="col-span-full p-6 text-center text-slate-500">
            No members match that search.
          </Card>
        )}
      </div>
    </section>
  );
}
