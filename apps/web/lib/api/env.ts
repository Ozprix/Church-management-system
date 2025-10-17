export function getApiBaseUrl(): string {
  if (typeof window === 'undefined') {
    return process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost/api';
  }
  return process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost/api';
}
