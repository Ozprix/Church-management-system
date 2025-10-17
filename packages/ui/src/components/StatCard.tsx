import { ReactNode } from 'react';
import { classNames } from '../utils/classNames';

export interface StatCardProps {
  title: string;
  value: string | number;
  helperText?: string;
  icon?: ReactNode;
  tone?: 'default' | 'success' | 'warning' | 'error';
}

const TONE_STYLES: Record<NonNullable<StatCardProps['tone']>, string> = {
  default: 'text-slate-900',
  success: 'text-emerald-600',
  warning: 'text-amber-600',
  error: 'text-rose-600',
};

export function StatCard({ title, value, helperText, icon, tone = 'default' }: StatCardProps) {
  return (
    <div className="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{title}</p>
          <p className={classNames('mt-2 text-2xl font-semibold', TONE_STYLES[tone])}>{value}</p>
        </div>
        {icon && <div className="text-emerald-500">{icon}</div>}
      </div>
      {helperText ? <p className="mt-3 text-xs text-slate-500">{helperText}</p> : null}
    </div>
  );
}
