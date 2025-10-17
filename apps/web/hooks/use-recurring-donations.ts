'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createRecurringDonationSchedule,
  deleteRecurringDonationSchedule,
  fetchRecurringDonationAttempts,
  fetchRecurringDonationSchedules,
  RecurringDonationSchedule,
  RecurringDonationAttempt,
  RecurringStatus,
  updateRecurringDonationSchedule,
} from '@/lib/api/recurring-donations';
import { useTenantId } from '@/lib/tenant';

export function useRecurringDonationSchedules(filters: { status?: RecurringStatus; page?: number } = {}) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['recurring-donations', tenantId, filters],
    queryFn: async () => {
      if (!tenantId) {
        return { data: [], meta: undefined } as { data: RecurringDonationSchedule[]; meta?: unknown };
      }
      return fetchRecurringDonationSchedules(tenantId, filters);
    },
    enabled: Boolean(tenantId),
  });
}

export function useRecurringDonationAttempts(scheduleId?: number) {
  const tenantId = useTenantId();

  return useQuery({
    queryKey: ['recurring-donation-attempts', tenantId, scheduleId],
    queryFn: async () => {
      if (!tenantId || !scheduleId) {
        return { data: [], meta: undefined } as { data: RecurringDonationAttempt[]; meta?: unknown };
      }
      return fetchRecurringDonationAttempts(tenantId, scheduleId);
    },
    enabled: Boolean(tenantId && scheduleId),
  });
}

export function useRecurringDonationMutation() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  const createMutation = useMutation({
    mutationFn: async (payload: Parameters<typeof createRecurringDonationSchedule>[1]) => {
      if (!tenantId) throw new Error('Missing tenant context');
      return createRecurringDonationSchedule(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Parameters<typeof updateRecurringDonationSchedule>[2] }) => {
      if (!tenantId) throw new Error('Missing tenant context');
      return updateRecurringDonationSchedule(tenantId, id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
      queryClient.invalidateQueries({ queryKey: ['recurring-donation-attempts'] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      if (!tenantId) throw new Error('Missing tenant context');
      return deleteRecurringDonationSchedule(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recurring-donations'] });
    },
  });

  return {
    createMutation,
    updateMutation,
    deleteMutation,
  };
}
