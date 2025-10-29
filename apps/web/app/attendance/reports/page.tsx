"use client";

import { FormEvent, Fragment, useMemo, useState } from "react";
import Link from "next/link";
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
} from "@church/ui";
import {
  AttendanceReportPayload,
  buildAttendanceReportExportUrl,
  buildAttendanceReportSnapshotDownloadUrl,
  fetchAttendanceReportSnapshots,
  type AttendanceReportSnapshot,
} from "@/lib/api/attendance-reports";
import { downloadFromApi } from "@/lib/download";
import { getApiBaseUrl } from "@/lib/api/env";
import { useTenantId } from "@/lib/tenant";
import {
  useAttendanceReports,
  useCreateAttendanceReport,
  useDeleteAttendanceReport,
  useRunAttendanceReport,
  useUpdateAttendanceReport,
} from "@/hooks/use-attendance-reports";
import { useServices } from "@/hooks/use-services";

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

const FREQUENCIES = [
  { value: "none", label: "On demand" },
  { value: "daily", label: "Daily" },
  { value: "weekly", label: "Weekly" },
  { value: "monthly", label: "Monthly" },
] as const;

const CHANNELS = [
  { value: "email", label: "Email" },
  { value: "download", label: "Download" },
  { value: "both", label: "Email + download" },
] as const;

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "scheduled", label: "Scheduled" },
  { value: "in_progress", label: "In progress" },
  { value: "completed", label: "Completed" },
  { value: "cancelled", label: "Cancelled" },
] as const;

type FrequencyValue = (typeof FREQUENCIES)[number]["value"];
type ChannelValue = (typeof CHANNELS)[number]["value"];

interface FormState {
  id: number | null;
  name: string;
  service_id: string;
  status: string;
  from: string;
  to: string;
  frequency: FrequencyValue;
  channel: ChannelValue;
  email_recipient: string;
}

const DEFAULT_FORM_STATE: FormState = {
  id: null,
  name: "",
  service_id: "",
  status: "",
  from: "",
  to: "",
  frequency: "none",
  channel: "email",
  email_recipient: "",
};

function formatFiltersSummary(
  filters: Record<string, unknown> | null,
  services: Array<{ id: number; name: string }>
): string {
  if (!filters) {
    return "All attendance";
  }

  const chips: string[] = [];
  if (filters.service_id) {
    const id = Number(filters.service_id);
    const serviceName = services.find((service) => service.id === id)?.name;
    chips.push(`Service: ${serviceName ?? `#${id}`}`);
  }
  if (filters.status) {
    chips.push(`Status: ${(filters.status as string).replace(/_/g, " ")}`);
  }
  if (filters.from) {
    chips.push(`From ${filters.from}`);
  }
  if (filters.to) {
    chips.push(`To ${filters.to}`);
  }

  return chips.length ? chips.join(" • ") : "All attendance";
}

export default function AttendanceReportsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();

  const [formState, setFormState] = useState<FormState>(DEFAULT_FORM_STATE);

  const { data: reports = [], isLoading } = useAttendanceReports();
  const createMutation = useCreateAttendanceReport();
  const updateMutation = useUpdateAttendanceReport(formState.id);
  const deleteMutation = useDeleteAttendanceReport();
  const runMutation = useRunAttendanceReport();
  const { data: servicesResponse } = useServices({ per_page: 100 });
  const services = useMemo(
    () => servicesResponse?.data ?? [],
    [servicesResponse?.data]
  );

  const isPending =
    createMutation.isPending ||
    updateMutation.isPending ||
    deleteMutation.isPending ||
    runMutation.isPending;

  const [expandedReportId, setExpandedReportId] = useState<number | null>(null);
  const [snapshotsCache, setSnapshotsCache] = useState<
    Record<number, AttendanceReportSnapshot[]>
  >({});
  const [snapshotsLoadingId, setSnapshotsLoadingId] = useState<number | null>(
    null
  );

  const isEditing = formState.id !== null;

  const handleChange = (field: keyof FormState, value: string) => {
    setFormState((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  const buildPayload = (): AttendanceReportPayload => {
    const filters: Record<string, unknown> = {};

    if (formState.service_id) {
      filters.service_id = Number(formState.service_id);
    }
    if (formState.status) {
      filters.status = formState.status;
    }
    if (formState.from) {
      filters.from = formState.from;
    }
    if (formState.to) {
      filters.to = formState.to;
    }

    return {
      name: formState.name.trim(),
      filters: Object.keys(filters).length ? filters : undefined,
      frequency: formState.frequency,
      channel: formState.channel,
      email_recipient: formState.email_recipient.trim() || null,
    };
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!formState.name.trim()) {
      pushToast({ title: "Name is required", variant: "error" });
      return;
    }

    const payload = buildPayload();

    try {
      if (isEditing) {
        await updateMutation.mutateAsync(payload);
        pushToast({ title: "Report updated", variant: "success" });
      } else {
        await createMutation.mutateAsync(payload);
        pushToast({ title: "Report saved", variant: "success" });
      }
      setFormState(DEFAULT_FORM_STATE);
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to save report";
      pushToast({ title: "Error", description: message, variant: "error" });
    }
  };

  const handleEdit = (reportId: number) => {
    const report = reports.find((item) => item.id === reportId);
    if (!report) return;

    const filters = (report.filters ?? {}) as Record<string, unknown>;

    setFormState({
      id: report.id,
      name: report.name,
      service_id: filters.service_id ? String(filters.service_id) : "",
      status: (filters.status as string) ?? "",
      from: (filters.from as string) ?? "",
      to: (filters.to as string) ?? "",
      frequency: report.frequency,
      channel: report.channel,
      email_recipient: report.email_recipient ?? "",
    });
  };

  const handleDelete = async (reportId: number) => {
    if (!window.confirm("Delete this report?")) {
      return;
    }

    try {
      await deleteMutation.mutateAsync(reportId);
      pushToast({ title: "Report deleted", variant: "success" });
      if (formState.id === reportId) {
        setFormState(DEFAULT_FORM_STATE);
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to delete report";
      pushToast({ title: "Error", description: message, variant: "error" });
    }
  };

  const handleRun = async (reportId: number) => {
    try {
      await runMutation.mutateAsync(reportId);
      pushToast({ title: "Report evaluated", variant: "success" });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to run report";
      pushToast({ title: "Error", description: message, variant: "error" });
    }
  };

  const handleExport = async (reportId: number) => {
    if (!tenantId) {
      pushToast({ title: "Unable to export", description: "Missing tenant context", variant: "error" });
      return;
    }

    try {
      const path = buildAttendanceReportExportUrl(reportId);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: "text/csv",
        "X-Tenant-ID": tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, `attendance-report-${reportId}.csv`);
      pushToast({ title: "Export ready", variant: "success" });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Export failed";
      pushToast({ title: "Error", description: message, variant: "error" });
    }
  };

  const handleDownloadSnapshot = async (reportId: number, snapshotId: number) => {
    if (!tenantId) {
      pushToast({ title: "Unable to download", description: "Missing tenant context", variant: "error" });
      return;
    }

    try {
      const path = buildAttendanceReportSnapshotDownloadUrl(reportId, snapshotId);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: "text/csv",
        "X-Tenant-ID": tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, `attendance-report-${reportId}-${snapshotId}.csv`);
      pushToast({ title: "Download started", variant: "success" });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to download snapshot";
      pushToast({ title: "Error", description: message, variant: "error" });
    }
  };

  const handleToggleSnapshots = async (reportId: number) => {
    if (expandedReportId === reportId) {
      setExpandedReportId(null);
      return;
    }

    if (!tenantId) {
      pushToast({
        title: "Unable to load downloads",
        description: "Missing tenant context",
        variant: "error",
      });
      return;
    }

    setExpandedReportId(reportId);

    if (snapshotsCache[reportId]) {
      return;
    }

    setSnapshotsLoadingId(reportId);
    try {
      const data = await fetchAttendanceReportSnapshots(tenantId, reportId);
      setSnapshotsCache((prev) => ({
        ...prev,
        [reportId]: data,
      }));
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Unable to load snapshots";
      pushToast({ title: "Error", description: message, variant: "error" });
      setExpandedReportId(null);
    } finally {
      setSnapshotsLoadingId(null);
    }
  };

  const currentFiltersSummary = useMemo(() => {
    const filters: Record<string, unknown> = {};
    if (formState.service_id) {
      filters.service_id = Number(formState.service_id);
    }
    if (formState.status) {
      filters.status = formState.status;
    }
    if (formState.from) {
      filters.from = formState.from;
    }
    if (formState.to) {
      filters.to = formState.to;
    }

    return formatFiltersSummary(
      Object.keys(filters).length ? filters : null,
      services
    );
  }, [formState.service_id, formState.status, formState.from, formState.to, services]);

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">
            Saved attendance reports
          </h2>
          <p className="text-sm text-slate-500">
            Automate attendance exports and email digests for leadership.
          </p>
        </div>
        <Link
          href="/attendance"
          className="text-sm text-emerald-600 hover:text-emerald-700"
        >
          Back to attendance
        </Link>
      </div>

      <Card className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">
            {isEditing ? "Edit report" : "Create report"}
          </h3>
          {isEditing ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setFormState(DEFAULT_FORM_STATE)}
            >
              Cancel edit
            </Button>
          ) : null}
        </div>

        <form className="grid gap-4 md:grid-cols-4" onSubmit={handleSubmit}>
          <div className="md:col-span-2">
            <Label htmlFor="attendance-report-name" required>
              Report name
            </Label>
            <Input
              id="attendance-report-name"
              value={formState.name}
              onChange={(event) => handleChange("name", event.target.value)}
              required
            />
          </div>
          <div>
            <Label htmlFor="attendance-report-frequency">Frequency</Label>
            <Select
              id="attendance-report-frequency"
              value={formState.frequency}
              onChange={(event) => handleChange("frequency", event.target.value)}
            >
              {FREQUENCIES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="attendance-report-channel">Delivery channel</Label>
            <Select
              id="attendance-report-channel"
              value={formState.channel}
              onChange={(event) => handleChange("channel", event.target.value)}
            >
              {CHANNELS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div className="md:col-span-4">
            <Label htmlFor="attendance-report-recipient">
              Email recipient (optional)
            </Label>
            <Input
              id="attendance-report-recipient"
              type="email"
              value={formState.email_recipient}
              onChange={(event) => handleChange("email_recipient", event.target.value)}
              placeholder="reports@example.com"
            />
          </div>
          <div>
            <Label htmlFor="attendance-report-service">Service</Label>
            <Select
              id="attendance-report-service"
              value={formState.service_id}
              onChange={(event) => handleChange("service_id", event.target.value)}
            >
              <option value="">All services</option>
              {services.map((service) => (
                <option key={service.id} value={service.id}>
                  {service.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="attendance-report-status">Status</Label>
            <Select
              id="attendance-report-status"
              value={formState.status}
              onChange={(event) => handleChange("status", event.target.value)}
            >
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="attendance-report-from">Starts from</Label>
            <Input
              id="attendance-report-from"
              type="date"
              value={formState.from}
              max={formState.to || undefined}
              onChange={(event) => handleChange("from", event.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="attendance-report-to">Starts to</Label>
            <Input
              id="attendance-report-to"
              type="date"
              value={formState.to}
              min={formState.from || undefined}
              onChange={(event) => handleChange("to", event.target.value)}
            />
          </div>
          <div className="md:col-span-4">
            <p className="text-xs text-slate-500">
              {currentFiltersSummary}
            </p>
          </div>
          <div className="md:col-span-4 flex justify-end gap-2">
            {isEditing ? (
              <Button
                type="button"
                variant="ghost"
                onClick={() => setFormState(DEFAULT_FORM_STATE)}
                disabled={isPending}
              >
                Cancel
              </Button>
            ) : null}
            <Button type="submit" disabled={isPending}>
              {isPending
                ? "Saving…"
                : isEditing
                ? "Update report"
                : "Save report"}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">
            Existing reports
          </h3>
          <p className="text-xs text-slate-500">{reports.length} total</p>
        </div>
        {isLoading ? (
          <p className="text-sm text-slate-500">Loading reports…</p>
        ) : reports.length === 0 ? (
          <p className="text-sm text-slate-500">
            No attendance reports yet. Create one to schedule exports or
            digests.
          </p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Name</TableHeaderCell>
                  <TableHeaderCell>Filters</TableHeaderCell>
                  <TableHeaderCell>Frequency</TableHeaderCell>
                  <TableHeaderCell>Channel</TableHeaderCell>
                  <TableHeaderCell>Last run</TableHeaderCell>
                  <TableHeaderCell>Latest download</TableHeaderCell>
                  <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {reports.map((report) => (
                  <Fragment key={report.id}>
                    <TableRow>
                      <TableCell className="font-medium text-slate-900">
                        {report.name}
                      </TableCell>
                      <TableCell className="text-sm text-slate-500">
                        {formatFiltersSummary(report.filters, services)}
                      </TableCell>
                      <TableCell className="text-sm text-slate-500">
                        {report.frequency}
                      </TableCell>
                      <TableCell className="text-sm text-slate-500">
                        {report.channel}
                      </TableCell>
                      <TableCell className="text-sm text-slate-500">
                        {report.last_run_at
                          ? new Date(report.last_run_at).toLocaleString()
                          : "Never"}
                      </TableCell>
                      <TableCell className="text-sm text-slate-500">
                        {report.latest_snapshot?.generated_at
                          ? new Date(
                              report.latest_snapshot.generated_at
                            ).toLocaleString()
                          : "No downloads"}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex flex-wrap justify-end gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleToggleSnapshots(report.id)}
                          >
                            {expandedReportId === report.id
                              ? "Hide downloads"
                              : "View downloads"}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              if (report.latest_snapshot) {
                                void handleDownloadSnapshot(
                                  report.id,
                                  report.latest_snapshot.id
                                );
                              }
                            }}
                            disabled={!report.latest_snapshot}
                          >
                            Download latest
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleRun(report.id)}
                            disabled={runMutation.isPending}
                          >
                            Run
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleExport(report.id)}
                          >
                            Export
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleEdit(report.id)}
                          >
                            Edit
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleDelete(report.id)}
                            disabled={deleteMutation.isPending}
                          >
                            Delete
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                    {expandedReportId === report.id ? (
                      <TableRow>
                        <TableCell colSpan={7}>
                          <SnapshotsPanel
                            reportId={report.id}
                            isLoading={snapshotsLoadingId === report.id}
                            snapshots={snapshotsCache[report.id]}
                            services={services}
                            onDownload={handleDownloadSnapshot}
                          />
                        </TableCell>
                      </TableRow>
                    ) : null}
                  </Fragment>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Card>
    </section>
  );
}

function SnapshotsPanel({
  reportId,
  isLoading,
  snapshots,
  services,
  onDownload,
}: {
  reportId: number;
  isLoading: boolean;
  snapshots?: AttendanceReportSnapshot[];
  services: Array<{ id: number; name: string }>;
  onDownload: (reportId: number, snapshotId: number) => void;
}) {
  if (isLoading && !snapshots) {
    return (
      <div className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
        Loading downloads…
      </div>
    );
  }

  if (!snapshots || snapshots.length === 0) {
    return (
      <div className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
        No saved downloads yet. The scheduler will add snapshots when this report
        runs.
      </div>
    );
  }

  return (
    <div className="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
      <p className="text-sm font-medium text-slate-700">
        Saved downloads ({snapshots.length})
      </p>
      <ul className="space-y-2">
        {snapshots.map((snapshot) => {
          const generatedLabel = formatDateTime(snapshot.generated_at);
          const expiresLabel = snapshot.expires_at
            ? formatDateTime(snapshot.expires_at)
            : "No expiry";
          const isExpired =
            snapshot.expires_at !== null &&
            new Date(snapshot.expires_at) < new Date();
          const filtersSummary = snapshot.filters
            ? formatFiltersSummary(snapshot.filters, services)
            : "All attendance";

          return (
            <li
              key={snapshot.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded border border-slate-200 bg-white p-3 text-sm"
            >
              <div className="space-y-1 text-left">
                <p className="font-medium text-slate-900">{generatedLabel}</p>
                <p className="text-xs text-slate-500">{filtersSummary}</p>
                <p
                  className={`text-xs ${
                    isExpired ? "text-rose-500" : "text-slate-500"
                  }`}
                >
                  {isExpired
                    ? "Expired"
                    : snapshot.expires_at
                    ? `Expires ${expiresLabel}`
                    : "Does not expire"}
                </p>
              </div>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                onClick={() => {
                  if (!isExpired) {
                    void onDownload(reportId, snapshot.id);
                  }
                }}
                disabled={isExpired}
              >
                {isExpired ? "Expired" : "Download"}
              </Button>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString();
}
