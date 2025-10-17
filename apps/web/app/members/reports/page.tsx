'use client';

import { FormEvent, useState } from 'react';
import Link from 'next/link';
import {
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
import { buildMemberReportExportUrl, MemberReportPayload } from '@/lib/api/member-reports';
import { useMemberReports, useCreateMemberReport, useUpdateMemberReport, useDeleteMemberReport } from '@/hooks/use-member-reports';
import { useTenantId } from '@/lib/tenant';
import { downloadFromApi } from '@/lib/download';
import { getApiBaseUrl } from '@/lib/api/env';

export default function MemberReportsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const { data: reports = [], isLoading } = useMemberReports();
  const createMutation = useCreateMemberReport();
  const [selectedReportId, setSelectedReportId] = useState<number | null>(null);
  const updateMutation = useUpdateMemberReport(selectedReportId);
  const deleteMutation = useDeleteMemberReport();

  const [name, setName] = useState('');
  const [status, setStatus] = useState('');
  const [stage, setStage] = useState('');
  const [joinedFrom, setJoinedFrom] = useState('');
  const [joinedTo, setJoinedTo] = useState('');
  const [withFamily, setWithFamily] = useState('');
  const [frequency, setFrequency] = useState('none');
  const [channel, setChannel] = useState('email');
  const [recipient, setRecipient] = useState('');

  const isPending = createMutation.isPending || updateMutation.isPending || deleteMutation.isPending;

  const resetForm = () => {
    setSelectedReportId(null);
    setName('');
    setStatus('');
    setStage('');
    setJoinedFrom('');
    setJoinedTo('');
    setWithFamily('');
    setFrequency('none');
    setChannel('email');
    setRecipient('');
  };

  const hydrateForm = (reportId: number) => {
    const report = reports.find((item) => item.id === reportId);
    if (!report) return;
    setSelectedReportId(report.id);
    setName(report.name);
    const filters = (report.filters ?? {}) as Record<string, unknown>;
    setStatus(String(filters.status ?? ''));
    setStage(String(filters.stage ?? ''));
    setJoinedFrom(String(filters.joined_from ?? ''));
    setJoinedTo(String(filters.joined_to ?? ''));
    setWithFamily(filters.with_family === null || filters.with_family === undefined ? '' : String(filters.with_family));
    setFrequency(report.frequency);
    setChannel(report.channel);
    setRecipient(report.email_recipient ?? '');
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!tenantId) {
      pushToast({ title: 'Missing tenant context', variant: 'error' });
      return;
    }

    if (!name.trim()) {
      pushToast({ title: 'Name is required', variant: 'error' });
      return;
    }

    const filters: Record<string, unknown> = {};
    if (status.trim()) filters.status = status.trim();
    if (stage.trim()) filters.stage = stage.trim();
    if (joinedFrom) filters.joined_from = joinedFrom;
    if (joinedTo) filters.joined_to = joinedTo;
    if (withFamily !== '') filters.with_family = withFamily === 'true';

    const payload: MemberReportPayload = {
      name: name.trim(),
      filters,
      frequency: frequency as MemberReportPayload['frequency'],
      channel: channel as MemberReportPayload['channel'],
      email_recipient: recipient.trim() || null,
    };

    try {
      if (selectedReportId) {
        await updateMutation.mutateAsync(payload);
        pushToast({ title: 'Report updated', variant: 'success' });
      } else {
        await createMutation.mutateAsync(payload);
        pushToast({ title: 'Report saved', variant: 'success' });
      }
      if (!selectedReportId) {
        resetForm();
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to save report';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const handleDelete = async (reportId: number) => {
    if (!tenantId) return;
    try {
      await deleteMutation.mutateAsync(reportId);
      pushToast({ title: 'Report deleted', variant: 'success' });
      if (selectedReportId === reportId) {
        resetForm();
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to delete report';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const handleExport = async (reportId: number) => {
    if (!tenantId) {
      pushToast({ title: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      const url = `${getApiBaseUrl()}${buildMemberReportExportUrl(reportId)}`;
      const headers: HeadersInit = {
        Accept: 'text/csv',
        'X-Tenant-ID': tenantId,
      };
      const token = process.env.NEXT_PUBLIC_API_TOKEN;
      if (token) headers.Authorization = `Bearer ${token}`;
      await downloadFromApi(url, { headers }, 'member-report.csv');
      pushToast({ title: 'Export ready', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Export failed';
      pushToast({ title: 'Export failed', description: message, variant: 'error' });
    }
  };

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Saved member reports</h2>
          <p className="text-sm text-slate-500">Schedule recurring exports or save favorite filter sets.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <Card className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{selectedReportId ? 'Edit report' : 'Create report'}</h3>
          {selectedReportId ? (
            <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
              Cancel edit
            </Button>
          ) : null}
        </div>
        <form className="grid gap-4 md:grid-cols-4" onSubmit={handleSubmit}>
          <div className="md:col-span-2">
            <Label htmlFor="report-name" required>Report name</Label>
            <Input id="report-name" value={name} onChange={(event) => setName(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="report-frequency">Frequency</Label>
            <Select id="report-frequency" value={frequency} onChange={(event) => setFrequency(event.target.value)}>
              <option value="none">On demand</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-channel">Delivery channel</Label>
            <Select id="report-channel" value={channel} onChange={(event) => setChannel(event.target.value)}>
              <option value="email">Email</option>
              <option value="download">Download</option>
              <option value="both">Email + download</option>
            </Select>
          </div>
          <div className="md:col-span-4">
            <Label htmlFor="report-recipient">Email recipient (optional)</Label>
            <Input
              id="report-recipient"
              type="email"
              placeholder="team@example.com"
              value={recipient}
              onChange={(event) => setRecipient(event.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="filter-status">Status</Label>
            <Input id="filter-status" value={status} onChange={(event) => setStatus(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="filter-stage">Stage</Label>
            <Input id="filter-stage" value={stage} onChange={(event) => setStage(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="filter-joined-from">Joined from</Label>
            <Input id="filter-joined-from" type="date" value={joinedFrom} onChange={(event) => setJoinedFrom(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="filter-joined-to">Joined to</Label>
            <Input id="filter-joined-to" type="date" value={joinedTo} onChange={(event) => setJoinedTo(event.target.value)} />
          </div>
          <div>
            <Label htmlFor="filter-with-family">Family</Label>
            <Select id="filter-with-family" value={withFamily} onChange={(event) => setWithFamily(event.target.value)}>
              <option value="">All members</option>
              <option value="true">Has family</option>
              <option value="false">No family</option>
            </Select>
          </div>
          <div className="md:col-span-4 flex items-center justify-end gap-3">
            <Button type="submit" loading={isPending}>
              {selectedReportId ? (isPending ? 'Saving…' : 'Save changes') : isPending ? 'Saving…' : 'Save report'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Saved reports</h3>
          <p className="text-xs text-slate-500">{reports.length} total</p>
        </div>
        {isLoading ? (
          <p className="text-sm text-slate-500">Loading reports…</p>
        ) : reports.length === 0 ? (
          <p className="text-sm text-slate-500">No reports yet. Use the form above to create one.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Name</TableHeaderCell>
                  <TableHeaderCell>Frequency</TableHeaderCell>
                  <TableHeaderCell>Channel</TableHeaderCell>
                  <TableHeaderCell>Last run</TableHeaderCell>
                  <TableHeaderCell></TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {reports.map((report) => (
                  <TableRow key={report.id}>
                    <TableCell className="font-medium text-slate-900">{report.name}</TableCell>
                    <TableCell className="text-sm text-slate-500">{report.frequency}</TableCell>
                    <TableCell className="text-sm text-slate-500">{report.channel}</TableCell>
                    <TableCell className="text-sm text-slate-500">{report.last_run_at ? new Date(report.last_run_at).toLocaleString() : '—'}</TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => hydrateForm(report.id)}>
                          Edit
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => handleExport(report.id)}>
                          Export
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-rose-600"
                          onClick={() => handleDelete(report.id)}
                        >
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Card>
    </section>
  );
}
