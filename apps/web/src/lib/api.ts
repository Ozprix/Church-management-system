export class UnauthorizedError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "UnauthorizedError";
  }
}

export function buildApiUrl(path: string): string {
  const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL?.replace(/\/+$/, "");

  if (!baseUrl) {
    throw new Error(
      "NEXT_PUBLIC_API_BASE_URL is not configured. Set it in your .env.local file."
    );
  }

  return `${baseUrl}${path.startsWith("/") ? "" : "/"}${path}`;
}

function tenantHeaders() {
  const tenantId = process.env.NEXT_PUBLIC_TENANT_ID;
  return tenantId ? { "X-Tenant-ID": tenantId } : {};
}

export async function apiFetch<T>(
  path: string,
  opts: RequestInit = {}
): Promise<T> {
  const url = buildApiUrl(path);

  const response = await fetch(url, {
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...tenantHeaders(),
      ...(opts.headers ?? {}),
    },
    ...opts,
  });

  if (response.status === 401 || response.status === 419) {
    throw new UnauthorizedError("Authentication required");
  }

  if (!response.ok) {
    const message = await response.text();
    throw new Error(
      `API request failed (${response.status}): ${message || response.statusText}`
    );
  }

  if (response.status === 204 || response.headers.get("content-length") === "0") {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export async function apiFormFetch<T>(
  path: string,
  formData: FormData,
  opts: RequestInit = {}
): Promise<T> {
  const url = buildApiUrl(path);

  const response = await fetch(url, {
    method: "POST",
    credentials: "include",
    body: formData,
    headers: {
      ...tenantHeaders(),
      ...(opts.headers ?? {}),
    },
    ...opts,
  });

  if (response.status === 401 || response.status === 419) {
    throw new UnauthorizedError("Authentication required");
  }

  if (!response.ok) {
    const message = await response.text();
    throw new Error(
      `API request failed (${response.status}): ${message || response.statusText}`
    );
  }

  if (response.status === 204 || response.headers.get("content-length") === "0") {
    return undefined as T;
  }

  return (await response.json()) as T;
}
