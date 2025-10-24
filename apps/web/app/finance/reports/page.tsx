'use client';

import { FormEvent, useState } from 'react';
import Link from 'next/link';
import {
  Badge,
  Button,
  Card,
  Input,
  Label,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
  useToast,
} from '@church/ui';
import { useFinanceReports, useCreateFinanceReport, useFinanceReportDownload } from '@/hooks/use-finance-reports';
import { FinanceReportType } from '@/lib/api/finance-reports';
import { useTenantId } from '@/lib/tenant';

const REPORT_TYPES: Array<{ value: FinanceReportType; label: string }> = [
  { value: 'donations', label: 'Donations summary' },
  { value: 'pledges', label: 'Pledges summary' },
  { value: 'monthly-statement', label: 'Monthly giving statement' },
  { value: 'balance-sheet', label: 'Balance sheet' },
  { value: 'donor-letters', label: 'Donor appreciation letters' },
  { value: 'donor-statement', label: 'Individual donor statement' },
];

export default function FinanceReportsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const { data: reports = [], isLoading } = useFinanceReports();
  const createReport = useCreateFinanceReport();
  const getDownloadUrl = useFinanceReportDownload();

  const [type, setType] = useState<FinanceReportType>('monthly-statement');
  const [memberId, setMemberId] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [month, setMonth] = useState('');

  const requiresMember = type === 'donor-statement';
  const showsMonth = type === 'monthly-statement';

  const handleRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const filters: Record<string, unknown> = {};
    if (from) filters.from = from;
    if (to) filters.to = to;
    if (showsMonth && month) filters.month = month;
    if (requiresMember && memberId) filters.member_id = Number(memberId);

    if (requiresMember && !memberId) {
      pushToast({ title: 'Member required', description: 'Select a member for donor statements.', variant: 'error' });
      return;
    }

    try {
      await createReport.mutateAsync({ type, filters: Object.keys(filters).length ? filters : undefined });
      pushToast({ title: 'Report queued', description: 'You will be notified once the PDF is ready.', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to queue report';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const handleDownload = async (reportId: number) => {
    if (!tenantId) {
      pushToast({ title: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const url = await getDownloadUrl(reportId);
      window.open(url, '_blank', 'noopener');
      pushToast({ title: 'Download starting', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to download report';
      pushToast({ title: 'Download failed', description: message, variant: 'error' });
    }
  };

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Branded finance exports</h2>
          <p className="text-sm text-slate-500">
            Generate branded PDFs for statements, balance sheets, and donor communications.
          </p>
        </div>
        <Link href="/finance" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to finance
        </Link>
      </div>

      <Card className="space-y-6" padding="lg">
        <div>
          <h3 className="text-lg font-semibold text-slate-900">Request a report</h3>
          <p className="text-sm text-slate-500">Queued PDFs are stored for quick download once completed.</p>
        </div>
        <form className="grid gap-4 md:grid-cols-4" onSubmit={handleRequest}>
          <div>
            <Label htmlFor="report-type" required>Report type</Label>
            <Select id="report-type" value={type} onChange={(event) => setType(event.target.value as FinanceReportType)}>
              {REPORT_TYPES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="report-from">From</Label>
            <Input id="report-from" type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="report-to">To</Label>
            <Input id="report-to" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
          </div>
          {showsMonth ? (
            <div>
              <Label htmlFor="report-month">Month (YYYY-MM)</Label>
              <Input id="report-month" type="month" value={month} onChange={(event) => setMonth(event.target.value)} />
            </div>
          ) : (
            <div>
              <Label htmlFor="report-member">Member ID (for donor statements)</Label>
              <Input
                id="report-member"
                type="number"
                min="1"
                value={memberId}
                onChange={(event) => setMemberId(event.target.value)}
                disabled={!requiresMember}
                placeholder={requiresMember ? 'Enter member ID' : 'Not required'}
              />
            </div>
          )}

          <div className="md:col-span-4 flex items-center justify-end gap-2">
            <Button type="submit" disabled={createReport.isPending}>
              {createReport.isPending ? 'Queuing…' : 'Queue PDF'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4" padding="lg">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Queued reports</h3>
          <p className="text-xs text-slate-500">Recently requested exports are listed below.</p>
        </div>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Report</TableHeaderCell>
                <TableHeaderCell>Status</TableHeaderCell>
                <TableHeaderCell>Requested</TableHeaderCell>
                <TableHeaderCell>Generated</TableHeaderCell>
                <TableHeaderCell>Actions</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={5}>Loading reports…</TableCell>
                </TableRow>
              ) : reports.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5}>No reports requested yet.</TableCell>
                </TableRow>
              ) : (
                reports.map((report) => (
                  <TableRow key={report.id}>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="font-medium text-slate-900">{report.label ?? report.type}</span>
                        {report.filters ? (
                          <span className="text-xs text-slate-500">{JSON.stringify(report.filters)}</span>
                        ) : null}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge
                        className={
                          report.status === 'failed'
                            ? 'bg-rose-100 text-rose-700'
                            : report.status === 'completed'
                              ? 'bg-emerald-100 text-emerald-700'
                              : undefined
                        }
                      >
                        {report.status}
                      </Badge>
                      {report.failure_reason ? (
                        <div className="mt-1 text-xs text-rose-600">{report.failure_reason}</div>
                      ) : null}
                    </TableCell>
                    <TableCell>{report.created_at ? new Date(report.created_at).toLocaleString() : '—'}</TableCell>
                    <TableCell>{report.generated_at ? new Date(report.generated_at).toLocaleString() : '—'}</TableCell>
                    <TableCell>
                      <Button
                        type="button"
                        size="sm"
                        disabled={report.status !== 'completed' || !report.download_url}
                        onClick={() => handleDownload(report.id)}
                      >
                        Download PDF
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>
    </section>
  );
}
