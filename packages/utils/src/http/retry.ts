export interface RetryOptions {
  retries?: number;
  delayMs?: number;
  shouldRetry?: (error: unknown, attempt: number) => boolean;
}

const DEFAULT_SHOULD_RETRY = (error: unknown) => {
  if (typeof error === 'object' && error && 'status' in (error as Record<string, unknown>)) {
    const status = (error as { status?: number }).status;
    return typeof status === 'number' && status >= 500;
  }
  return true;
};

export async function retry<T>(operation: () => Promise<T>, options: RetryOptions = {}): Promise<T> {
  const {
    retries = 2,
    delayMs = 250,
    shouldRetry = DEFAULT_SHOULD_RETRY
  } = options;

  let attempt = 0;
  let lastError: unknown;

  while (attempt <= retries) {
    try {
      return await operation();
    } catch (error) {
      lastError = error;
      if (attempt === retries || !shouldRetry(error, attempt)) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, delayMs));
      attempt += 1;
    }
  }

  throw lastError;
}
