import { forwardRef, TextareaHTMLAttributes } from 'react';
import { classNames } from '../utils/classNames';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { className, rows = 3, 'aria-invalid': ariaInvalid, ...props },
  ref
) {
  const isInvalid = ariaInvalid === true || ariaInvalid === 'true';
  return (
    <textarea
      ref={ref}
      rows={rows}
      aria-invalid={ariaInvalid}
      className={classNames(
        'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:bg-slate-100',
        isInvalid &&
          'border-rose-400 focus:border-rose-500 focus:ring-rose-100 focus:ring-2 focus:ring-offset-0',
        className
      )}
      {...props}
    />
  );
});
