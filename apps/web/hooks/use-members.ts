'use client';

import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchMembers, MemberFilters } from '@/lib/api/members';
import { useTenantId } from '@/lib/tenant';

export function useMembers(filters: MemberFilters = {}) {
  const tenantId = useTenantId();
  const normalizedFilters = useMemo(
    () => ({
      search: filters.search ?? '',
      status: filters.status ?? '',
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 10,
      sort: filters.sort ?? 'last_name',
      direction: filters.direction ?? 'asc',
    }),
    [filters.search, filters.status, filters.page, filters.per_page, filters.sort, filters.direction]
  );

  return useQuery({
    queryKey: ['members', tenantId, normalizedFilters],
    queryFn: async () => {
      if (!tenantId) {
        return { data: [], meta: undefined };
      }

      return fetchMembers(tenantId, {
        search: normalizedFilters.search || undefined,
        status: normalizedFilters.status || undefined,
        page: normalizedFilters.page,
        per_page: normalizedFilters.per_page,
        sort: normalizedFilters.sort,
        direction: normalizedFilters.direction,
      });
    },
    enabled: Boolean(tenantId),
  });
}
