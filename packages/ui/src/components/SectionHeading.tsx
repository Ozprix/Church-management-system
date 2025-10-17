import type { ReactNode } from 'react';
import { classNames } from '../utils/classNames';

export interface SectionHeadingProps {
  title: ReactNode;
  description?: ReactNode;
  align?: 'left' | 'center';
  className?: string;
}

export function SectionHeading({ title, description, align = 'left', className }: SectionHeadingProps) {
  const alignment = align === 'center' ? 'text-center' : 'text-left';
  return (
    <div className={classNames('flex flex-col gap-2', alignment, className)}>
      <h2 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h2>
      {description && <p className="text-sm text-slate-600">{description}</p>}
    </div>
  );
}
