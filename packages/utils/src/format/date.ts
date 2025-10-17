export interface DateFormatOptions {
  locale?: string;
  withTime?: boolean;
  timeZone?: string;
}

/**
 * Formats an ISO date string or Date instance into a human friendly string using Intl APIs.
 */
export function formatDate(value: string | Date, options: DateFormatOptions = {}): string {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    throw new Error('Invalid date input for formatDate');
  }

  const { locale = 'en-US', withTime = false, timeZone } = options;
  const dateOptions: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone
  };

  if (withTime) {
    dateOptions.hour = 'numeric';
    dateOptions.minute = 'numeric';
  }

  return new Intl.DateTimeFormat(locale, dateOptions).format(date);
}
