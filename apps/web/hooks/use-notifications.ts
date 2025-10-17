'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createNotification,
  createNotificationTemplate,
  deleteNotificationTemplate,
  fetchNotifications,
  fetchNotificationTemplates,
  NotificationFilters,
  NotificationPayload,
  NotificationTemplatePayload,
  requeueNotification,
  updateNotificationTemplate,
} from '@/lib/api/notifications';
import { useTenantId } from '@/lib/tenant';

export function useNotifications(filters: NotificationFilters = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['notifications', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchNotifications(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useNotificationTemplates(filters: { channel?: 'sms' | 'email'; search?: string; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['notification-templates', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchNotificationTemplates(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateNotification() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: NotificationPayload) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return createNotification(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useRequeueNotification() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload?: Partial<NotificationPayload> }) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return requeueNotification(tenantId, id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useCreateNotificationTemplate() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: NotificationTemplatePayload) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return createNotificationTemplate(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}

export function useUpdateNotificationTemplate(templateId: number | null) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: Partial<NotificationTemplatePayload>) => {
      if (!tenantId || templateId === null) throw new Error('Missing template id');
      return updateNotificationTemplate(tenantId, templateId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}

export function useDeleteNotificationTemplate() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (templateId: number) => {
      if (!tenantId) throw new Error('Missing tenant id');
      await deleteNotificationTemplate(tenantId, templateId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}
