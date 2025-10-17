import { describe, expect, it } from 'vitest';
import { retry } from '../http/retry';

describe('retry', () => {
  it('retries the provided operation until it succeeds', async () => {
    let attempts = 0;
    const result = await retry(async () => {
      attempts += 1;
      if (attempts < 3) {
        throw new Error('transient');
      }
      return 'success';
    }, { retries: 5, delayMs: 10 });

    expect(result).toBe('success');
    expect(attempts).toBe(3);
  });

  it('bubbles the last error when retries exhausted', async () => {
    await expect(
      retry(async () => {
        throw Object.assign(new Error('server'), { status: 503 });
      }, { retries: 1, delayMs: 5 })
    ).rejects.toThrowError('server');
  });
});
