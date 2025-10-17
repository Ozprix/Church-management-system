export interface CurrencyFormatOptions {
  currency: string;
  locale?: string;
  minimumFractionDigits?: number;
}

/** Formats a numeric amount into a localized currency string. */
export function formatCurrency(amount: number, options: CurrencyFormatOptions): string {
  const { currency, locale = 'en-US', minimumFractionDigits } = options;
  const formatter = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits
  });
  return formatter.format(amount);
}
