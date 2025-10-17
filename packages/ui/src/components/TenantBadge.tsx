import type { ReactNode } from 'react';
import { BADGE_VARIANTS, type BadgeVariant } from '../theme';
import { classNames } from '../utils/classNames';

export interface TenantBadgeProps {
  name: string;
  variant?: BadgeVariant;
  icon?: ReactNode;
  className?: string;
}

export function TenantBadge({ name, variant = 'default', icon, className }: TenantBadgeProps) {
  return (
    <span
      className={classNames(
        'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
        BADGE_VARIANTS[variant],
        className
      )}
      data-tenant-badge
    >
      {icon}
      <span>{name}</span>
    </span>
  );
}
