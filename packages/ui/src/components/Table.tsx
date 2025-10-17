import {
  HTMLAttributes,
  ReactNode,
  TableHTMLAttributes,
  TdHTMLAttributes,
  ThHTMLAttributes,
} from 'react';
import { classNames } from '../utils/classNames';

export function TableContainer({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={classNames('overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm', className)}>
      {children}
    </div>
  );
}

export function Table({ children, className, ...props }: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <table
      className={classNames('min-w-full divide-y divide-slate-200 text-sm text-slate-700', className)}
      {...props}
    >
      {children}
    </table>
  );
}

export function TableHead({ children, className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return (
    <thead className={classNames('bg-slate-50 text-xs uppercase tracking-wider text-slate-500', className)} {...props}>
      {children}
    </thead>
  );
}

export function TableHeaderCell({ children, className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th className={classNames('px-4 py-3 text-left font-medium', className)} {...props}>
      {children}
    </th>
  );
}

export function TableBody({ children, className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return (
    <tbody className={classNames('divide-y divide-slate-200 bg-white', className)} {...props}>
      {children}
    </tbody>
  );
}

export function TableRow({ children, className, ...props }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr className={classNames('hover:bg-slate-50 transition-colors', className)} {...props}>
      {children}
    </tr>
  );
}

export function TableCell({ children, className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return (
    <td className={classNames('px-4 py-3 align-top', className)} {...props}>
      {children}
    </td>
  );
}
