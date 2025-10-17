import { getApiBaseUrl } from '@/lib/api/env';

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;
  body?: unknown;

  constructor(message: string, status: number, errors?: Record<string, string[]>, body?: unknown) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.body = body;
  }
}

export async function apiFetch<T>(path: string, options: RequestInit = {}, tenantId?: string): Promise<T> {
  const baseUrl = getApiBaseUrl();
  const headers = new Headers(options.headers);
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
  if (!isFormData && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  const apiToken = process.env.NEXT_PUBLIC_API_TOKEN;
  if (apiToken && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${apiToken}`);
  }
  if (tenantId) {
    headers.set('X-Tenant-ID', tenantId);
  }

  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers,
    cache: 'no-store',
  });

  const text = await response.text();
  let body: unknown = undefined;

  if (text) {
    try {
      body = JSON.parse(text);
    } catch (_error) {
      body = text;
    }
  }

  if (!response.ok) {
    const message =
      (body as { message?: string } | undefined)?.message ?? response.statusText ?? 'Request failed';
    const errors = (body as { errors?: Record<string, string[]> } | undefined)?.errors;
    throw new ApiError(message, response.status, errors, body);
  }

  return body as T;
}
