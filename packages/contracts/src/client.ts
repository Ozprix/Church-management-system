import type { Paths } from './generated';

export interface ApiClientConfig {
  baseUrl: string;
  defaultHeaders?: Record<string, string>;
  fetchImpl?: typeof fetch;
}

export class ApiClient {
  private readonly baseUrl: string;
  private readonly defaultHeaders: Record<string, string>;
  private readonly fetchImpl: typeof fetch;

  constructor(config: ApiClientConfig) {
    this.baseUrl = config.baseUrl.replace(/\/$/, '');
    this.defaultHeaders = {
      'Content-Type': 'application/json',
      ...config.defaultHeaders
    };
    this.fetchImpl = config.fetchImpl ?? fetch;
  }

  async listMembers(
    tenantId: string,
    query?: Paths['/tenants/{tenantId}/members']['get']['parameters']['query']
  ): Promise<Paths['/tenants/{tenantId}/members']['get']['responses'][200]> {
    const searchParams = new URLSearchParams();
    if (query?.status) {
      searchParams.set('status', query.status);
    }

    const response = await this.fetchImpl(
      this.buildUrl(`/tenants/${tenantId}/members`, searchParams),
      {
        method: 'GET',
        headers: this.defaultHeaders
      }
    );

    if (!response.ok) {
      throw await this.toError(response);
    }

    return (await response.json()) as Paths['/tenants/{tenantId}/members']['get']['responses'][200];
  }

  async createMember(
    tenantId: string,
    payload: Paths['/tenants/{tenantId}/members']['post']['requestBody']['content']['application/json']
  ): Promise<Paths['/tenants/{tenantId}/members']['post']['responses'][201]> {
    const response = await this.fetchImpl(this.buildUrl(`/tenants/${tenantId}/members`), {
      method: 'POST',
      headers: this.defaultHeaders,
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw await this.toError(response);
    }

    return (await response.json()) as Paths['/tenants/{tenantId}/members']['post']['responses'][201];
  }

  async recordDonation(
    tenantId: string,
    payload: Paths['/tenants/{tenantId}/donations']['post']['requestBody']['content']['application/json']
  ): Promise<Paths['/tenants/{tenantId}/donations']['post']['responses'][201]> {
    const response = await this.fetchImpl(this.buildUrl(`/tenants/${tenantId}/donations`), {
      method: 'POST',
      headers: this.defaultHeaders,
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw await this.toError(response);
    }

    return (await response.json()) as Paths['/tenants/{tenantId}/donations']['post']['responses'][201];
  }

  private buildUrl(path: string, searchParams?: URLSearchParams): string {
    const url = new URL(`${this.baseUrl}${path}`);
    if (searchParams && Array.from(searchParams.keys()).length > 0) {
      url.search = searchParams.toString();
    }
    return url.toString();
  }

  private async toError(response: Response): Promise<Error> {
    let details: unknown;
    try {
      details = await response.clone().json();
    } catch {
      details = await response.text();
    }

    const error = new Error(`API request failed with status ${response.status}`);
    Object.assign(error, { status: response.status, details });
    return error;
  }
}

export function createApiClient(config: ApiClientConfig): ApiClient {
  return new ApiClient(config);
}
