'use client';

import { PaymentMethod } from '@/lib/api/payment-methods';
import {
  Badge,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
} from '@church/ui';

interface PaymentMethodListProps {
  methods: PaymentMethod[];
  onSetDefault: (id: number) => void;
  onDelete: (id: number) => void;
}

export function PaymentMethodList({ methods, onSetDefault, onDelete }: PaymentMethodListProps) {
  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Member</TableHeaderCell>
            <TableHeaderCell>Type</TableHeaderCell>
            <TableHeaderCell>Provider</TableHeaderCell>
            <TableHeaderCell>Reference</TableHeaderCell>
            <TableHeaderCell className="text-right">Actions</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {methods.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="py-6 text-center text-sm text-slate-500">
                No payment methods have been captured yet.
              </TableCell>
            </TableRow>
          ) : (
            methods.map((method) => (
              <TableRow key={method.id}>
                <TableCell>
                  <div>
                    <p className="text-sm font-medium text-slate-800">
                      {method.member ? `${method.member.first_name} ${method.member.last_name}` : 'All members'}
                    </p>
                    <p className="text-xs text-slate-500">Method #{method.id}</p>
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Badge variant={method.is_default ? 'success' : 'info'}>
                      {method.type.replace('_', ' ')}
                    </Badge>
                    {method.is_default ? <span className="text-xs text-emerald-600">Default</span> : null}
                  </div>
                </TableCell>
                <TableCell>
                  <p className="text-sm text-slate-700">{method.brand ?? method.provider ?? '—'}</p>
                  <p className="text-xs text-slate-500">
                    {method.last_four ? `•••• ${method.last_four}` : method.provider ?? 'N/A'}
                  </p>
                </TableCell>
                <TableCell>{method.provider_reference ?? '—'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-2">
                    {!method.is_default && (
                      <Button variant="secondary" size="sm" onClick={() => onSetDefault(method.id)}>
                        Set default
                      </Button>
                    )}
                    <Button variant="ghost" size="sm" onClick={() => onDelete(method.id)}>
                      Remove
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
