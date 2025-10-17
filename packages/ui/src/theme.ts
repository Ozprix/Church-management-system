export type ButtonVariant = 'primary' | 'secondary' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

export const BUTTON_BASE_CLASSES =
  'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

export const BUTTON_VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary: 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-600',
  secondary: 'bg-slate-100 text-slate-900 hover:bg-slate-200 focus-visible:ring-slate-400',
  ghost: 'bg-transparent text-emerald-700 hover:bg-emerald-50 focus-visible:ring-emerald-200'
};

export const BUTTON_SIZE_CLASSES: Record<ButtonSize, string> = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-12 px-6 text-base'
};

export const CARD_BASE_CLASSES = 'rounded-xl border border-slate-200 bg-white shadow-sm';
export const CARD_PADDING_CLASSES = {
  sm: 'p-4',
  md: 'p-6',
  lg: 'p-8'
} as const;

export type CardPadding = keyof typeof CARD_PADDING_CLASSES;

export const BADGE_VARIANTS = {
  default: 'bg-emerald-100 text-emerald-800',
  subtle: 'bg-slate-100 text-slate-600',
  warning: 'bg-amber-100 text-amber-800'
} as const;

export type BadgeVariant = keyof typeof BADGE_VARIANTS;
