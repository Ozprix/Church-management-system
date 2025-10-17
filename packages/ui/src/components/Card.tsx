import type { ReactNode } from 'react';
import { CARD_BASE_CLASSES, CARD_PADDING_CLASSES, type CardPadding } from '../theme';
import { classNames } from '../utils/classNames';

export interface CardProps {
  title?: ReactNode;
  subtitle?: ReactNode;
  padding?: CardPadding;
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}

export function Card({ title, subtitle, padding = 'md', actions, children, className }: CardProps) {
  return (
    <section className={classNames(CARD_BASE_CLASSES, CARD_PADDING_CLASSES[padding], className)}>
      {(title || subtitle || actions) && (
        <header className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div>
            {title && <h3 className="text-lg font-semibold text-slate-900">{title}</h3>}
            {subtitle && <p className="text-sm text-slate-600">{subtitle}</p>}
          </div>
          {actions && <div className="flex items-center gap-2">{actions}</div>}
        </header>
      )}
      <div>{children}</div>
    </section>
  );
}
