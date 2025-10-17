import { describe, expect, it } from 'vitest';
import { formatCurrency } from '../format/currency';

describe('formatCurrency', () => {
  it('formats USD amounts with cents', () => {
    expect(formatCurrency(1234.5, { currency: 'USD' })).toBe('$1,234.50');
  });

  it('formats Ghanaian cedi without decimals', () => {
    expect(formatCurrency(2500, { currency: 'GHS', locale: 'en-GH', minimumFractionDigits: 0 })).toBe('GH₵2,500');
  });
});
