"use client";

import { FormEvent, useMemo, useState } from "react";
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
  AnalyticsAreaChartCard,
  AnalyticsBarChartCard,
} from "@/components/analytics/widgets";
import { useAttendanceAnalytics } from "@/hooks/use-attendance-analytics";
import { useServices } from "@/hooks/use-services";
import { useTenantId } from "@/lib/tenant";
import {
  buildAttendanceAnalyticsExportUrl,
  AttendanceAnalyticsFilters,
  type AttendanceAnalytics,
} from "@/lib/api/attendance";
import { downloadFromApi } from "@/lib/download";
import { getApiBaseUrl } from "@/lib/api/env";

const API_TOKEN = process.env.NEXT_PUBLIC_API_TOKEN;

const STATUS_OPTIONS: Array<{ value: string; label: string }> = [
  { value: "", label: "All statuses" },
  { value: "scheduled", label: "Scheduled" },
  { value: "in_progress", label: "In progress" },
  { value: "completed", label: "Completed" },
  { value: "cancelled", label: "Cancelled" },
];

export default function AttendanceAnalyticsPage() {
  const tenantId = useTenantId();
  const { pushToast } = useToast();
  const [filters, setFilters] = useState<AttendanceAnalyticsFilters>({});
  const [formState, setFormState] = useState({
    service_id: "",
    status: "",
    from: "",
    to: "",
  });

  const { data: servicesResponse } = useServices({ per_page: 100 });
  const services = useMemo(
    () => servicesResponse?.data ?? [],
    [servicesResponse?.data]
  );

  const { data, isLoading } = useAttendanceAnalytics(filters);

  const summary = data?.summary ?? {
    gatherings_tracked: 0,
    total_check_ins: 0,
    attendance_rate: 0,
    average_present: 0,
  };

  const statusTotals = useMemo(() => {
    const trend = data?.trend ?? [];
    return trend.reduce(
      (acc, item) => {
        acc.present += item.present ?? 0;
        acc.absent += item.absent ?? 0;
        acc.excused += item.excused ?? 0;
        return acc;
      },
      { present: 0, absent: 0, excused: 0 }
    );
  }, [data?.trend]);

  const statusBreakdown = useMemo(
    () => [
      { label: "Present", value: statusTotals.present },
      { label: "Absent", value: statusTotals.absent },
      { label: "Excused", value: statusTotals.excused },
    ],
    [statusTotals]
  );

  const weeklyAttendance = useMemo(
    () =>
      (data?.trend ?? []).map((item) => ({
        label: item.label,
        value: item.present,
      })),
    [data?.trend]
  );

  const serviceBreakdown = useMemo(
    () => data?.service_breakdown ?? [],
    [data?.service_breakdown]
  );

  const memberSegments = useMemo(
    () => data?.member_segments ?? [],
    [data?.member_segments]
  );

  const departmentSegments = useMemo(
    () => data?.department_segments ?? [],
    [data?.department_segments]
  );

  const ageBands = useMemo(() => data?.age_bands ?? [], [data?.age_bands]);

  const handleExport = async () => {
    if (!tenantId) {
      pushToast({
        title: "Unable to export",
        description: "Missing tenant context",
        variant: "error",
      });
      return;
    }

    try {
      const path = buildAttendanceAnalyticsExportUrl(filters);
      const url = `${getApiBaseUrl()}${path}`;
      const headers: HeadersInit = {
        Accept: "text/csv",
        "X-Tenant-ID": tenantId,
      };
      if (API_TOKEN) {
        headers.Authorization = `Bearer ${API_TOKEN}`;
      }

      await downloadFromApi(url, { headers }, "attendance-analytics.csv");
      pushToast({
        title: "Export ready",
        description: "Attendance CSV download has started.",
        variant: "success",
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Export failed";
      pushToast({
        title: "Export failed",
        description: message,
        variant: "error",
      });
    }
  };

  const handleFormChange = (field: keyof typeof formState, value: string) => {
    setFormState((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  const handleApplyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const next: AttendanceAnalyticsFilters = {};
    if (formState.service_id) {
      next.service_id = Number(formState.service_id);
    }
    if (formState.status) {
      next.status = formState.status;
    }
    if (formState.from) {
      next.from = formState.from;
    }
    if (formState.to) {
      next.to = formState.to;
    }
    setFilters(next);
  };

  const handleResetFilters = () => {
    setFormState({ service_id: "", status: "", from: "", to: "" });
    setFilters({});
  };

  const activeFilters = useMemo(() => {
    const chips: string[] = [];
    if (filters.service_id) {
      const serviceName = services.find((service) => service.id === filters.service_id)?.name;
      chips.push(`Service: ${serviceName ?? `#${filters.service_id}`}`);
    }
    if (filters.status) {
      chips.push(
        `Status: ${filters.status.replace(/_/g, " ")}`
      );
    }
    if (filters.from) {
      chips.push(`From ${filters.from}`);
    }
    if (filters.to) {
      chips.push(`To ${filters.to}`);
    }
    return chips;
  }, [filters, services]);

  const windowLabel = filters.from || filters.to
    ? `Filtered range: ${filters.from ?? "—"} → ${filters.to ?? "—"}`
    : "Rolling eight-week monitoring window.";

  const hasFormValues = useMemo(
    () =>
      Boolean(
        formState.service_id ||
          formState.status ||
          formState.from ||
          formState.to
      ),
    [formState]
  );

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">
            Attendance analytics
          </h2>
          <p className="text-sm text-slate-500">
            Understand participation trends, track high-performing gatherings,
            and flag members who need follow-up.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" variant="secondary" onClick={handleExport}>
            Export summary CSV
          </Button>
          <Link
            href="/attendance"
            className="text-sm text-emerald-600 hover:text-emerald-700"
          >
            Back to attendance
          </Link>
        </div>
      </div>

      <Card className="space-y-4">
        <form
          className="grid gap-4 md:grid-cols-4"
          onSubmit={handleApplyFilters}
        >
          <div>
            <Label htmlFor="attendance-filter-service">Service</Label>
            <Select
              id="attendance-filter-service"
              value={formState.service_id}
              onChange={(event) => handleFormChange("service_id", event.target.value)}
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
            <Label htmlFor="attendance-filter-status">Status</Label>
            <Select
              id="attendance-filter-status"
              value={formState.status}
              onChange={(event) => handleFormChange("status", event.target.value)}
            >
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="attendance-filter-from">From</Label>
            <Input
              id="attendance-filter-from"
              type="date"
              value={formState.from}
              max={formState.to || undefined}
              onChange={(event) => handleFormChange("from", event.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="attendance-filter-to">To</Label>
            <Input
              id="attendance-filter-to"
              type="date"
              value={formState.to}
              min={formState.from || undefined}
              onChange={(event) => handleFormChange("to", event.target.value)}
            />
          </div>
          <div className="md:col-span-4 flex justify-end gap-2">
            <Button
              type="button"
              variant="ghost"
              onClick={handleResetFilters}
              disabled={!hasFormValues && !activeFilters.length}
            >
              Reset
            </Button>
            <Button type="submit">Apply filters</Button>
          </div>
        </form>
        {activeFilters.length > 0 ? (
          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span className="font-semibold uppercase tracking-wide">
              Active filters:
            </span>
            {activeFilters.map((chip) => (
              <span
                key={chip}
                className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600"
              >
                {chip}
              </span>
            ))}
          </div>
        ) : (
          <p className="text-xs text-slate-500">
            Showing the default eight-week attendance window. Apply filters to
            refine the analytics.
          </p>
        )}
      </Card>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="space-y-1">
          <p className="text-xs uppercase tracking-wide text-slate-500">
            Gatherings tracked
          </p>
          <p className="text-2xl font-semibold text-slate-900">
            {summary.gatherings_tracked ?? "—"}
          </p>
          <p className="text-xs text-slate-500">{windowLabel}</p>
        </Card>
        <Card className="space-y-1">
          <p className="text-xs uppercase tracking-wide text-slate-500">
            Total check-ins
          </p>
          <p className="text-2xl font-semibold text-slate-900">
            {summary.total_check_ins ?? "—"}
          </p>
          <p className="text-xs text-slate-500">
            Sum of all present attendees captured.
          </p>
        </Card>
        <Card className="space-y-1">
          <p className="text-xs uppercase tracking-wide text-slate-500">
            Attendance rate
          </p>
          <p className="text-2xl font-semibold text-slate-900">
            {summary.attendance_rate !== undefined
              ? `${summary.attendance_rate}%`
              : "—"}
          </p>
          <p className="text-xs text-slate-500">
            Present vs. total attendance records.
          </p>
        </Card>
        <Card className="space-y-1">
          <p className="text-xs uppercase tracking-wide text-slate-500">
            Average present
          </p>
          <p className="text-2xl font-semibold text-slate-900">
            {summary.average_present ?? "—"}
          </p>
          <p className="text-xs text-slate-500">
            Average present attendees per gathering.
          </p>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <AnalyticsAreaChartCard
          title="Weekly present attendees"
          data={weeklyAttendance}
          helperText="Present headcount per recorded gathering week."
          stroke="#047857"
          fill="#bbf7d0"
        />
        <AnalyticsBarChartCard
          title="Attendance status breakdown"
          data={statusBreakdown}
          helperText="Total records in the rolling window."
          color="#1e293b"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Top gatherings (present)
            </h3>
            <p className="text-xs text-slate-500">Past eight weeks</p>
          </header>
          <TopGatheringsTable gatherings={data?.top_gatherings ?? []} />
        </Card>
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Follow-up suggestions
            </h3>
            <p className="text-xs text-slate-500">
              Members with repeated absences
            </p>
          </header>
          <FollowupCandidatesTable
            candidates={data?.followup_candidates ?? []}
          />
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Service breakdown
            </h3>
            <p className="text-xs text-slate-500">
              Attendance totals per service
            </p>
          </header>
          <ServiceBreakdownTable breakdown={serviceBreakdown} />
        </Card>
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Member segments
            </h3>
            <p className="text-xs text-slate-500">Membership status insights</p>
          </header>
          <MemberSegmentsTable segments={memberSegments} />
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Department engagement
            </h3>
            <p className="text-xs text-slate-500">Check-ins by ministry stage</p>
          </header>
          <DepartmentSegmentsTable segments={departmentSegments} />
        </Card>
        <Card className="space-y-4">
          <header className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">
              Age distribution
            </h3>
            <p className="text-xs text-slate-500">Attendance by age band</p>
          </header>
          <AgeBandTable bands={ageBands} />
        </Card>
      </div>

      <Card className="space-y-3">
        <header className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">
            Reporting & exports
          </h3>
          <p className="text-xs text-slate-500">
            Use the attendance dashboard for daily management and bulk exports
            for archival reporting.
          </p>
        </header>
        <p className="text-sm text-slate-500">
          Need individual gathering exports or PDF packages? Visit the main
          attendance dashboard to queue CSV/PDF bundles per gathering.
        </p>
        <div className="flex flex-wrap gap-2 text-sm text-slate-500">
          <span>Quick links:</span>
          <Link
            href="/attendance"
            className="text-emerald-600 hover:text-emerald-700"
          >
            Attendance dashboard
          </Link>
          <span>·</span>
          <Link
            href="/attendance/kiosk"
            className="text-emerald-600 hover:text-emerald-700"
          >
            Kiosk mode
          </Link>
        </div>
      </Card>

      {isLoading ? (
        <p className="text-sm text-slate-500">Loading analytics…</p>
      ) : null}
    </section>
  );
}

function TopGatheringsTable({
  gatherings,
}: {
  gatherings: AttendanceAnalytics["top_gatherings"];
}) {
  if (gatherings.length === 0) {
    return <p className="text-sm text-slate-500">No gathering data available.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Gathering</TableHeaderCell>
            <TableHeaderCell>Service</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">
              Attendance rate
            </TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {gatherings.map((gathering) => (
            <TableRow key={gathering.id}>
              <TableCell>
                <div className="flex flex-col">
                  <span className="font-medium text-slate-900">
                    {gathering.name}
                  </span>
                  <span className="text-xs text-slate-500">
                    {gathering.starts_at
                      ? new Date(gathering.starts_at).toLocaleString()
                      : "—"}
                  </span>
                </div>
              </TableCell>
              <TableCell className="text-sm text-slate-500">
                {gathering.service ?? "—"}
              </TableCell>
              <TableCell className="text-right font-medium text-slate-900">
                {gathering.present}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {gathering.attendance_rate}%
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function FollowupCandidatesTable({
  candidates,
}: {
  candidates: AttendanceAnalytics["followup_candidates"];
}) {
  if (candidates.length === 0) {
    return <p className="text-sm text-slate-500">All caught up! No follow-up needed.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Member</TableHeaderCell>
            <TableHeaderCell className="text-right">Absent</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Attendance</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {candidates.map((candidate) => {
            const attendanceRate =
              candidate.total > 0
                ? Math.round((candidate.present / candidate.total) * 100)
                : 0;

            return (
              <TableRow key={candidate.member_id}>
                <TableCell className="font-medium text-slate-900">
                  {candidate.member_name}
                </TableCell>
                <TableCell className="text-right text-slate-500">
                  {candidate.absent}
                </TableCell>
                <TableCell className="text-right text-slate-500">
                  {candidate.present}
                </TableCell>
                <TableCell className="text-right text-slate-500">
                  {attendanceRate}%
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function ServiceBreakdownTable({
  breakdown,
}: {
  breakdown: AttendanceAnalytics["service_breakdown"];
}) {
  if (!breakdown.length) {
    return <p className="text-sm text-slate-500">No service-level data yet.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Service</TableHeaderCell>
            <TableHeaderCell className="text-right">Gatherings</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Absent</TableHeaderCell>
            <TableHeaderCell className="text-right">Excused</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {breakdown.map((service) => (
            <TableRow key={service.service_id ?? service.service_name}>
              <TableCell className="font-medium text-slate-900">
                {service.service_name}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {service.gatherings_tracked}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {service.present}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {service.absent}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {service.excused}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function MemberSegmentsTable({
  segments,
}: {
  segments: AttendanceAnalytics["member_segments"];
}) {
  if (!segments.length) {
    return <p className="text-sm text-slate-500">No member segment data available.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Segment</TableHeaderCell>
            <TableHeaderCell className="text-right">Members</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Absent</TableHeaderCell>
            <TableHeaderCell className="text-right">Excused</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {segments.map((segment) => (
            <TableRow key={segment.membership_status ?? segment.label}>
              <TableCell className="font-medium text-slate-900">
                {segment.label}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.members}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.present}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.absent}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.excused}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function DepartmentSegmentsTable({
  segments,
}: {
  segments: AttendanceAnalytics["department_segments"];
}) {
  if (!segments.length) {
    return <p className="text-sm text-slate-500">No department segments to show.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Department</TableHeaderCell>
            <TableHeaderCell className="text-right">Members</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Absent</TableHeaderCell>
            <TableHeaderCell className="text-right">Excused</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {segments.map((segment) => (
            <TableRow key={segment.department ?? segment.label}>
              <TableCell className="font-medium text-slate-900">
                {segment.label}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.members}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.present}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.absent}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {segment.excused}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function AgeBandTable({
  bands,
}: {
  bands: AttendanceAnalytics["age_bands"];
}) {
  if (!bands.length) {
    return <p className="text-sm text-slate-500">No age data recorded.</p>;
  }

  return (
    <TableContainer>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeaderCell>Age band</TableHeaderCell>
            <TableHeaderCell className="text-right">Members</TableHeaderCell>
            <TableHeaderCell className="text-right">Present</TableHeaderCell>
            <TableHeaderCell className="text-right">Absent</TableHeaderCell>
            <TableHeaderCell className="text-right">Excused</TableHeaderCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {bands.map((band) => (
            <TableRow key={band.band}>
              <TableCell className="font-medium text-slate-900">
                {band.label}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {band.members}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {band.present}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {band.absent}
              </TableCell>
              <TableCell className="text-right text-sm text-slate-500">
                {band.excused}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
