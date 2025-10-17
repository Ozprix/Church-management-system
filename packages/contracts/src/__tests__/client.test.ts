import { describe, expect, it, vi } from 'vitest';
import { createApiClient } from '../client';

const BASE_URL = 'https://api.example.com';

function createFetchMock(json: unknown, status = 200) {
  return vi.fn(async () =>
    new Response(JSON.stringify(json), {
      status,
      headers: { 'Content-Type': 'application/json' }
    })
  ) as unknown as typeof fetch;
}

describe('ApiClient', () => {
  it('lists members with typed response', async () => {
    const mockResponse = { data: [{ id: '123', tenantId: 'grace', firstName: 'Ada', lastName: 'Lovelace', status: 'active' as const }] };
    const fetchMock = createFetchMock(mockResponse);
    const client = createApiClient({ baseUrl: BASE_URL, fetchImpl: fetchMock });

    const result = await client.listMembers('grace');
    expect(result).toEqual(mockResponse);
    expect(fetchMock).toHaveBeenCalledWith(`${BASE_URL}/tenants/grace/members`, expect.any(Object));
  });

  it('throws enriched error when response not ok', async () => {
    const fetchMock = vi.fn(async () => new Response('oops', { status: 500 }));
    const client = createApiClient({ baseUrl: BASE_URL, fetchImpl: fetchMock });

    await expect(client.createMember('grace', { firstName: 'Test', lastName: 'User', status: 'active' })).rejects.toMatchObject({
      status: 500
    });
  });
});
