'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createPaymentMethod,
  deletePaymentMethod,
  fetchPaymentMethods,
  PaymentMethodFilters,
  PaymentMethodPayload,
  updatePaymentMethod,
} from '@/lib/api/payment-methods';
import { useTenantId } from '@/lib/tenant';
import { useToast } from '@church/ui';

export function usePaymentMethods(filters: PaymentMethodFilters = {}) {
  const tenantId = useTenantId();
  const normalizedFilters = useMemo(
    () => ({
      member_id: filters.member_id,
      type: filters.type,
      search: filters.search ?? '',
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 20,
    }),
    [filters.member_id, filters.type, filters.search, filters.page, filters.per_page]
  );

  return useQuery({
    queryKey: ['payment-methods', tenantId, normalizedFilters],
    queryFn: () => {
      if (!tenantId) {
        return Promise.resolve({ data: [], meta: undefined });
      }

      return fetchPaymentMethods(tenantId, normalizedFilters);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreatePaymentMethod() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (payload: PaymentMethodPayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return createPaymentMethod(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-methods', tenantId] });
      pushToast({ title: 'Payment method saved', variant: 'success' });
    },
    onError: (error: unknown) => {
      pushToast({ title: 'Failed to save payment method', variant: 'error' });
      throw error;
    },
  });
}

export function useUpdatePaymentMethod() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: PaymentMethodPayload }) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return updatePaymentMethod(tenantId, id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-methods', tenantId] });
      pushToast({ title: 'Payment method updated', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to update payment method', variant: 'error' });
    },
  });
}

export function useDeletePaymentMethod() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  return useMutation({
    mutationFn: (id: number) => {
      if (!tenantId) {
        throw new Error('Missing tenant context');
      }

      return deletePaymentMethod(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-methods', tenantId] });
      pushToast({ title: 'Payment method removed', variant: 'success' });
    },
    onError: () => {
      pushToast({ title: 'Failed to remove payment method', variant: 'error' });
    },
  });
}
