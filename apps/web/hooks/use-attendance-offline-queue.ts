'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  addOfflineAttendanceJob,
  ATTENDANCE_QUEUE_STORAGE_KEY,
  getOfflineAttendanceQueue,
  removeOfflineAttendanceJob,
  replaceOfflineAttendanceQueue,
  type OfflineAttendanceJob,
} from '@/lib/offline/attendanceQueue';

interface UseAttendanceOfflineQueueOptions {
  tenantId?: string | null;
  gatheringUuid?: string | null;
  onSynced?: (job: OfflineAttendanceJob) => void;
  flushHandler: (job: OfflineAttendanceJob) => Promise<void>;
}

export function useAttendanceOfflineQueue(options: UseAttendanceOfflineQueueOptions) {
  const { tenantId, gatheringUuid, flushHandler, onSynced } = options;
  const [queue, setQueue] = useState<OfflineAttendanceJob[]>(() => getOfflineAttendanceQueue());
  const flushingRef = useRef(false);

  const filteredQueue = useMemo(() => {
    return queue.filter((job) => {
      if (tenantId && job.tenantId !== tenantId) {
        return false;
      }

      if (gatheringUuid && job.gatheringUuid !== gatheringUuid) {
        return false;
      }

      return true;
    });
  }, [queue, tenantId, gatheringUuid]);

  const syncState = useCallback(() => {
    setQueue(getOfflineAttendanceQueue());
  }, []);

  const enqueue = useCallback(
    (job: Omit<OfflineAttendanceJob, 'id' | 'createdAt'>) => {
      const stored = addOfflineAttendanceJob(job);
      syncState();
      return stored;
    },
    [syncState]
  );

  const markSynced = useCallback(
    (id: string) => {
      removeOfflineAttendanceJob(id);
      syncState();
    },
    [syncState]
  );

  const flush = useCallback(async () => {
    if (flushingRef.current) {
      return;
    }

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      return;
    }

    flushingRef.current = true;

    try {
      let currentQueue = getOfflineAttendanceQueue();

      for (const job of currentQueue) {
        if (tenantId && job.tenantId !== tenantId) {
          continue;
        }

        if (gatheringUuid && job.gatheringUuid !== gatheringUuid) {
          continue;
        }

        try {
          await flushHandler(job);
          removeOfflineAttendanceJob(job.id);
          onSynced?.(job);
        } catch (error) {
          break;
        } finally {
          currentQueue = getOfflineAttendanceQueue();
        }
      }
    } finally {
      flushingRef.current = false;
      syncState();
    }
  }, [flushHandler, gatheringUuid, onSynced, syncState, tenantId]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const handleStorage = (event: StorageEvent) => {
      if (event.key === ATTENDANCE_QUEUE_STORAGE_KEY) {
        syncState();
      }
    };

    window.addEventListener('storage', handleStorage);

    return () => {
      window.removeEventListener('storage', handleStorage);
    };
  }, [syncState]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const handleOnline = () => {
      flush();
    };

    window.addEventListener('online', handleOnline);

    flush();

    return () => {
      window.removeEventListener('online', handleOnline);
    };
  }, [flush]);

  return {
    queue: filteredQueue,
    enqueue,
    markSynced,
    flush,
    replaceQueue: (jobs: OfflineAttendanceJob[]) => {
      replaceOfflineAttendanceQueue(jobs);
      syncState();
    },
  };
}
