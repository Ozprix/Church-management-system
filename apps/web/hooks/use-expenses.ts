'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  approveExpense,
  createExpense,
  deleteExpense,
  ExpenseFilters,
  ExpensePayload,
  reimburseExpense,
  submitExpense,
  updateExpense,
  fetchExpenses,
} from '@/lib/api/expenses';
import { useTenantId } from '@/lib/tenant';
import { useToast } from '@church/ui';

export function useExpenses(filters: ExpenseFilters = {}) {
  const tenantId = useTenantId();
  const normalizedFilters = useMemo(
    () => ({
      status: filters.status,
      submitted_by: filters.submitted_by,
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 20,
    }),
    [filters.status, filters.submitted_by, filters.page, filters.per_page]
  );

  return useQuery({
    queryKey: ['expenses', tenantId, normalizedFilters],
    queryFn: () => {
      if (!tenantId) {
        return Promise.resolve({ data: [], meta: undefined });
      }

      return fetchExpenses(tenantId, normalizedFilters);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (payload: ExpensePayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return createExpense(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense saved', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to save expense', variant: 'error' });
    },
  });
}

export function useUpdateExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<ExpensePayload> }) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return updateExpense(tenantId, id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense updated', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to update expense', variant: 'error' });
    },
  });
}

export function useDeleteExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (id: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return deleteExpense(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense removed', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to delete expense', variant: 'error' });
    },
  });
}

export function useSubmitExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (id: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return submitExpense(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense submitted for approval', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to submit expense', variant: 'error' });
    },
  });
}

export function useApproveExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (id: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return approveExpense(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense approved', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to approve expense', variant: 'error' });
    },
  });
}

export function useReimburseExpense() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (id: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return reimburseExpense(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses', tenantId] });
      pushToast({ title: 'Expense reimbursed', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to reimburse expense', variant: 'error' });
    },
  });
}
