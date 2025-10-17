/**
 * Formats a phone number in E.164 or national format by applying basic grouping.
 * For production workloads you should back this with libphonenumber or a telecom provider API.
 */
export function formatPhoneNumber(value: string, countryCode: '+1' | '+44' | '+233' = '+1'): string {
  const digits = value.replace(/[^0-9]/g, '');

  if (!digits) {
    return '';
  }

  const normalized = `${countryCode}${digits}`;

  if (countryCode === '+1' && digits.length === 10) {
    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
  }

  if (countryCode === '+233' && digits.length === 9) {
    return `${digits.slice(0, 3)} ${digits.slice(3, 6)} ${digits.slice(6)}`;
  }

  return normalized;
}
