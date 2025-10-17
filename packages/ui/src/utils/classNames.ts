export type ClassValue = string | number | null | undefined | false;

/**
 * Utility helper to compose conditional class names without relying on a third-party dependency.
 */
export function classNames(...values: ClassValue[]): string {
  return values
    .filter((value): value is string | number => Boolean(value))
    .map((value) => String(value).trim())
    .filter(Boolean)
    .join(' ');
}
