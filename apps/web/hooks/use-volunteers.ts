'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createVolunteerAssignment,
  fetchVolunteerAssignments,
  fetchVolunteerAvailability,
  fetchVolunteerRoles,
  fetchVolunteerSignups,
  fetchVolunteerTeams,
  swapVolunteerAssignments,
  updateVolunteerSignup,
  upsertVolunteerAvailability,
  deleteVolunteerSignup,
  type VolunteerAssignmentPayload,
  type VolunteerAvailabilityPayload,
  type VolunteerSignupUpdatePayload,
} from '@/lib/api/volunteers';
import { useTenantId } from '@/lib/tenant';

export function useVolunteerRoles(filters: { search?: string; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['volunteer-roles', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchVolunteerRoles(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useVolunteerTeams(filters: { search?: string; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['volunteer-teams', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchVolunteerTeams(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useVolunteerAssignments(filters: { member_id?: number; status?: string; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['volunteer-assignments', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchVolunteerAssignments(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useCreateVolunteerAssignment() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: VolunteerAssignmentPayload) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return createVolunteerAssignment(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer-assignments'] });
    },
  });
}

export function useSwapVolunteerAssignment() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ sourceId, targetId }: { sourceId: number; targetId: number }) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return swapVolunteerAssignments(tenantId, sourceId, targetId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer-assignments'] });
    },
  });
}

export function useVolunteerAvailability(filters: { member_id?: number; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['volunteer-availability', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchVolunteerAvailability(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useUpsertVolunteerAvailability() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: VolunteerAvailabilityPayload) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return upsertVolunteerAvailability(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer-availability'] });
    },
  });
}

export function useVolunteerSignups(filters: { status?: string; stage?: string; page?: number; per_page?: number } = {}) {
  const tenantId = useTenantId();
  const normalized = useMemo(() => ({ ...filters }), [filters]);

  return useQuery({
    queryKey: ['volunteer-signups', tenantId, normalized],
    queryFn: async () => {
      if (!tenantId) return { data: [], meta: undefined };
      return fetchVolunteerSignups(tenantId, normalized);
    },
    enabled: Boolean(tenantId),
  });
}

export function useUpdateVolunteerSignup() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: VolunteerSignupUpdatePayload }) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return updateVolunteerSignup(tenantId, id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer-signups'] });
      queryClient.invalidateQueries({ queryKey: ['volunteer-roles'] });
    },
  });
}

export function useDeleteVolunteerSignup() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (id: number) => {
      if (!tenantId) throw new Error('Missing tenant id');
      return deleteVolunteerSignup(tenantId, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer-signups'] });
      queryClient.invalidateQueries({ queryKey: ['volunteer-roles'] });
    },
  });
}
