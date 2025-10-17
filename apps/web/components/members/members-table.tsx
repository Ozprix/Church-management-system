'use client';

import clsx from 'clsx';
import Link from 'next/link';
import { MemberSummary, MembersMeta } from '@/lib/api/members';
import {
  Button,
  TableContainer,
  Table,
  TableHead,
  TableHeaderCell,
  TableBody,
  TableRow,
  TableCell,
} from '@church/ui';

interface MembersTableProps {
  members: MemberSummary[];
  meta?: MembersMeta;
  isLoading?: boolean;
  sort?: string;
  direction?: 'asc' | 'desc';
  visibleColumns: string[];
  onSortChange?: (column: string, direction: 'asc' | 'desc') => void;
  onPageChange?: (page: number) => void;
}

export function MembersTable({
  members,
  meta,
  isLoading,
  sort,
  direction = 'asc',
  visibleColumns,
  onSortChange,
  onPageChange,
}: MembersTableProps) {
  if (isLoading) {
    return <p className="text-slate-500">Loading members…</p>;
  }

  if (!members.length) {
    return <p className="text-slate-500">No members found.</p>;
  }

  const currentPage = meta?.current_page ?? 1;
  const perPage = meta?.per_page ?? members.length;
  const total = meta?.total ?? members.length;
  const lastPage = meta?.last_page ?? Math.max(1, Math.ceil(total / perPage));
  const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const end = total === 0 ? 0 : Math.min(currentPage * perPage, total);

  const handleSort = (column: string, isSortable: boolean) => {
    if (!isSortable || !onSortChange) {
      return;
    }

    const nextDirection: 'asc' | 'desc' = sort === column && direction === 'asc' ? 'desc' : 'asc';
    onSortChange(column, nextDirection);
  };

  return (
    <div className="space-y-4">
      <TableContainer className="border-0 shadow-none">
        <Table>
          <TableHead>
            <TableRow>
              <SortableHeader
                label="Name"
                active={sort === 'last_name'}
                direction={direction}
                onClick={() => handleSort('last_name', true)}
              />
              {visibleColumns.includes('preferred_name') && (
                <TableHeaderCell>Preferred Name</TableHeaderCell>
              )}
              {visibleColumns.includes('stage') && <SortableHeader
                  label="Membership Stage"
                  active={sort === 'membership_stage'}
                  direction={direction}
                  onClick={() => handleSort('membership_stage', true)}
                />}
              {visibleColumns.includes('status') && (
                <SortableHeader
                  label="Status"
                  active={sort === 'membership_status'}
                  direction={direction}
                  onClick={() => handleSort('membership_status', true)}
                />
              )}
              {visibleColumns.includes('contact') && (
                <TableHeaderCell>Primary Contact</TableHeaderCell>
              )}
              <TableHeaderCell className="text-right">&nbsp;</TableHeaderCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {members.map((member) => (
              <TableRow key={member.uuid}>
                <TableCell className="font-medium text-slate-900">
                  {member.first_name} {member.last_name}
                </TableCell>
                {visibleColumns.includes('preferred_name') && (
                  <TableCell>{member.preferred_name ?? '—'}</TableCell>
                )}
                {visibleColumns.includes('stage') && (
                  <TableCell>{member.membership_stage ?? '—'}</TableCell>
                )}
                {visibleColumns.includes('status') && (
                  <TableCell>
                    <span
                      className={clsx(
                        'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium capitalize',
                        statusStyles(member.membership_status)
                      )}
                    >
                      {member.membership_status}
                    </span>
                  </TableCell>
                )}
                {visibleColumns.includes('contact') && (
                  <TableCell>
                    {member.preferred_contact?.value ? (
                      <span className="text-sm text-slate-700">
                        {member.preferred_contact.value}{' '}
                        <span className="text-xs uppercase text-slate-400">
                          ({member.preferred_contact.type})
                        </span>
                      </span>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </TableCell>
                )}
                <TableCell className="text-right">
                  <Link href={`/members/${member.uuid}`} className="text-emerald-600 hover:text-emerald-700">
                    View
                  </Link>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <div className="flex flex-col gap-4 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
        <p>
          Showing {start}–{end} of {total} members
        </p>
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={currentPage <= 1}
            onClick={() => onPageChange && onPageChange(currentPage - 1)}
          >
            Previous
          </Button>
          <span>
            Page {currentPage} of {lastPage}
          </span>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={currentPage >= lastPage}
            onClick={() => onPageChange && onPageChange(currentPage + 1)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  );
}

function statusStyles(status: string) {
  switch (status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-700';
    case 'inactive':
      return 'bg-slate-100 text-slate-600';
    case 'visitor':
      return 'bg-blue-100 text-blue-700';
    case 'prospect':
      return 'bg-amber-100 text-amber-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

interface SortableHeaderProps {
  label: string;
  active: boolean;
  direction: 'asc' | 'desc';
  onClick: () => void;
}

function SortableHeader({ label, active, direction, onClick }: SortableHeaderProps) {
  return (
    <TableHeaderCell>
      <button
        type="button"
        onClick={onClick}
        className={clsx(
          'flex items-center gap-1 text-left text-sm font-medium',
          active ? 'text-emerald-700' : 'text-slate-600'
        )}
      >
        {label}
        <span aria-hidden="true" className="text-xs text-slate-400">
          {active ? (direction === 'asc' ? '↑' : '↓') : '↕'}
        </span>
      </button>
    </TableHeaderCell>
  );
}
