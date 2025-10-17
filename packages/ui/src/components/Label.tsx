import { LabelHTMLAttributes, ReactNode } from 'react';
import { classNames } from '../utils/classNames';

export interface LabelProps extends Omit<LabelHTMLAttributes<HTMLLabelElement>, 'children'> {
  children: ReactNode;
  required?: boolean;
}

export function Label({ className, children, required = false, ...props }: LabelProps) {
  return (
    <label className={classNames('mb-1 block text-sm font-medium text-slate-700', className)} {...props}>
      <span className="inline-flex items-center gap-1">
        {children}
        {required && (
          <span aria-hidden="true" className="text-rose-600">
            *
          </span>
        )}
      </span>
    </label>
  );
}
